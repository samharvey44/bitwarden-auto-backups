<?php

namespace Tests\Feature\Listeners;

use App\Events\BackupSucceeded;
use App\Listeners\PruneOldBackups;
use App\Models\Backup;
use App\Models\BackupFile;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOldBackupsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listens_for_successful_backups(): void
    {
        Event::fake();

        Event::assertListening(BackupSucceeded::class, PruneOldBackups::class);
    }

    public function test_deletes_the_oldest_successful_backups_and_their_files_beyond_the_retention_limit(): void
    {
        Storage::fake('backups');
        $vault = Vault::factory()->create(['retain_backups_count' => 2]);
        [$oldest, $older, $latest] = Backup::factory()->for($vault)->succeeded()->count(3)->sequence(
            ['completed_at' => now()->subDays(2)],
            ['completed_at' => now()->subDay()],
            ['completed_at' => now()],
        )->create();
        $oldestFiles = collect([
            BackupFile::factory()->for($oldest)->create(['disk' => 'backups', 'path' => 'backups/oldest.encrypted.json']),
            BackupFile::factory()->for($oldest)->json()->create(['disk' => 'backups', 'path' => 'backups/oldest.json']),
        ]);
        $olderFile = BackupFile::factory()->for($older)->create(['disk' => 'backups', 'path' => 'backups/older.encrypted.json']);
        $oldestFiles->each(fn (BackupFile $file) => Storage::disk('backups')->put($file->path, 'oldest'));
        Storage::disk('backups')->put($olderFile->path, 'older');

        (new PruneOldBackups)->handle(new BackupSucceeded($latest));

        $this->assertModelMissing($oldest);
        $oldestFiles->each(function (BackupFile $file): void {
            $this->assertModelMissing($file);
            Storage::disk('backups')->assertMissing($file->path);
        });

        $this->assertModelExists($older);
        $this->assertModelExists($olderFile);
        Storage::disk('backups')->assertExists($olderFile->path);

        $this->assertModelExists($latest);
    }

    public function test_never_deletes_failed_or_unfinished_backups(): void
    {
        $vault = Vault::factory()->create(['retain_backups_count' => 1]);
        $failed = Backup::factory()->for($vault)->failed()->create(['completed_at' => now()->subDays(3)]);
        $pending = Backup::factory()->for($vault)->create();
        $latest = Backup::factory()->for($vault)->succeeded()->create();

        (new PruneOldBackups)->handle(new BackupSucceeded($latest));

        $this->assertModelExists($failed);
        $this->assertModelExists($pending);
        $this->assertModelExists($latest);
    }

    public function test_only_deletes_backups_of_the_same_vault(): void
    {
        $vault = Vault::factory()->create(['retain_backups_count' => 1]);
        $otherVaultBackup = Backup::factory()->succeeded()->create(['completed_at' => now()->subDays(5)]);
        $latest = Backup::factory()->for($vault)->succeeded()->create();

        (new PruneOldBackups)->handle(new BackupSucceeded($latest));

        $this->assertModelExists($otherVaultBackup);
    }
}
