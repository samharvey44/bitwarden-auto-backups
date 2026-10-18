<?php

namespace Tests\Feature\Jobs;

use App\Enums\BackupFormat;
use App\Enums\BackupStatus;
use App\Events\BackupFailed;
use App\Events\BackupSucceeded;
use App\Jobs\BackupVault;
use App\Models\Backup;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\DTOs\VaultExport;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

class BackupVaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stores_the_encrypted_json_export_with_its_password_and_marks_the_backup_succeeded(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create(['encrypted_json_export_password' => 'bitwarden-export-secret']);
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();

        $this->runJob($backup);

        $expectedPath = "{$vault->id}/2026-10-13_120000-{$backup->id}.encrypted.json";
        Storage::disk('backups')->assertExists($expectedPath, '{"format":"encrypted_json"}');

        $file = $backup->files()->sole();
        $this->assertSame(BackupFormat::EncryptedJson, $file->format);
        $this->assertSame('backups', $file->disk);
        $this->assertSame($expectedPath, $file->path);
        $this->assertSame(27, $file->size_bytes);
        $this->assertSame('bitwarden-export-secret', $file->password);

        $backup->refresh();
        $this->assertSame(BackupStatus::Succeeded, $backup->status);
        $this->assertSame('2026-10-13 12:00:00', $backup->started_at->toDateTimeString());
        $this->assertSame('2026-10-13 12:00:00', $backup->completed_at->toDateTimeString());

        $this->assertSame('2026-10-13 12:00:00', $vault->refresh()->last_backed_up_at->toDateTimeString());
    }

    public function test_stores_the_unencrypted_json_export_as_a_plain_file_when_it_has_no_password(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withoutEncryptedJsonExport()->withJsonExport()->create();
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();

        $this->runJob($backup);

        $file = $backup->files()->sole();
        $this->assertSame(BackupFormat::Json, $file->format);
        $this->assertSame("{$vault->id}/2026-10-13_120000-{$backup->id}.json", $file->path);
        $this->assertNull($file->password);

        Storage::disk('backups')->assertExists($file->path, '{"format":"json"}');
    }

    public function test_stores_the_unencrypted_json_export_in_an_aes_256_zip_when_it_has_a_password(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withoutEncryptedJsonExport()->withJsonExport('zip-secret')->create();
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();

        $this->runJob($backup);

        $file = $backup->files()->sole();
        $this->assertSame("{$vault->id}/2026-10-13_120000-{$backup->id}.zip", $file->path);
        $this->assertSame('zip-secret', $file->password);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backups')->path($file->path)));
        $this->assertSame(ZipArchive::EM_AES_256, $zip->statName('vault.json')['encryption_method']);
        $this->assertFalse($zip->getFromName('vault.json'));

        $zip->setPassword('zip-secret');
        $this->assertSame('{"format":"json"}', $zip->getFromName('vault.json'));
        $zip->close();
    }

    public function test_backs_up_every_enabled_format_in_one_run(): void
    {
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withJsonExport()->create();
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();

        $this->runJob($backup);

        $this->assertSame(
            [BackupFormat::Json, BackupFormat::EncryptedJson],
            $backup->files()->orderBy('id')->get()->pluck('format')->all(),
        );
    }

    public function test_files_keep_the_password_they_were_made_with_after_the_vault_password_changes(): void
    {
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create(['encrypted_json_export_password' => 'original-secret']);
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();
        $this->runJob($backup);

        $vault->update(['encrypted_json_export_password' => 'new-secret']);

        $this->assertSame('original-secret', $backup->files()->sole()->password);
    }

    public function test_leaves_nothing_on_the_working_disk(): void
    {
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withJsonExport('zip-secret')->create();
        $backup = Backup::factory()->for($vault)->create();
        $this->fakeExport();

        $this->runJob($backup);

        $this->assertSame([], Storage::disk('bitwarden-cli')->allFiles());
    }

    public function test_announces_the_successful_backup(): void
    {
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $backup = Backup::factory()->create();
        $this->fakeExport();
        Event::fake([BackupSucceeded::class]);

        $this->runJob($backup);

        Event::assertDispatched(BackupSucceeded::class, fn (BackupSucceeded $event): bool => $event->backup->is($backup));
    }

    public function test_failed_export_stores_nothing_and_rethrows_for_a_retry(): void
    {
        Storage::fake('backups');
        Storage::fake('bitwarden-cli');
        $backup = Backup::factory()->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exportVault')->once()->andThrow(new BitwardenCliException('Unlock failed.'));
        });
        Event::fake([BackupSucceeded::class, BackupFailed::class]);

        try {
            $this->runJob($backup);
            $this->fail('Expected the job to rethrow the CLI failure.');
        } catch (BitwardenCliException) {
            //
        }

        $this->assertSame([], Storage::disk('backups')->allFiles());
        $this->assertDatabaseCount('backup_files', 0);
        $this->assertSame(BackupStatus::Running, $backup->refresh()->status);
        Event::assertNotDispatched(BackupSucceeded::class);
        Event::assertNotDispatched(BackupFailed::class);
    }

    public function test_fails_without_exporting_when_no_formats_are_enabled(): void
    {
        Event::fake([BackupFailed::class]);
        $backup = Backup::factory()->for(Vault::factory()->withoutExports())->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('exportVault');
        });

        $this->runJob($backup);

        $backup->refresh();
        $this->assertSame(BackupStatus::Failed, $backup->status);
        $this->assertSame('The vault has no backup formats enabled.', $backup->error);
        Event::assertDispatched(BackupFailed::class, fn (BackupFailed $event): bool => $event->backup->is($backup));
    }

    public function test_fails_without_exporting_when_encrypted_json_has_no_password(): void
    {
        $backup = Backup::factory()->for(Vault::factory()->state(['encrypted_json_export_password' => null]))->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('exportVault');
        });

        $this->runJob($backup);

        $backup->refresh();
        $this->assertSame(BackupStatus::Failed, $backup->status);
        $this->assertSame('Encrypted JSON backups are enabled but no export password is set.', $backup->error);
    }

    public function test_marks_the_backup_failed_with_the_reason_once_retries_are_exhausted(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        Event::fake([BackupFailed::class]);
        $backup = Backup::factory()->create(['status' => BackupStatus::Running]);

        (new BackupVault($backup))->failed(new BitwardenCliException('Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.'));

        $backup->refresh();
        $this->assertSame(BackupStatus::Failed, $backup->status);
        $this->assertSame('Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.', $backup->error);
        $this->assertSame('2026-10-13 12:00:00', $backup->completed_at->toDateTimeString());
        Event::assertDispatched(BackupFailed::class, fn (BackupFailed $event): bool => $event->backup->is($backup));
    }

    /**
     * Replace the Bitwarden CLI with one that exports a small JSON document
     * naming each requested format.
     */
    private function fakeExport(): void
    {
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exportVault')
                ->once()
                ->andReturnUsing(fn (Vault $vault, BackupFormat ...$formats): array => array_map(
                    fn (BackupFormat $format): VaultExport => new VaultExport($format, "{\"format\":\"{$format->value}\"}"),
                    $formats,
                ));
        });
    }

    private function runJob(Backup $backup): void
    {
        (new BackupVault($backup))->handle(app(BitwardenCli::class));
    }
}
