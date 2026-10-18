<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Backup;
use App\Models\BackupFile;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteVaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deletes_the_vault_its_backups_and_their_files(): void
    {
        Storage::fake('backups');
        $vault = Vault::factory()->create(['name' => 'Personal']);
        $file = $this->storedFile($vault);
        $backup = $file->backup;
        $otherFile = $this->storedFile(Vault::factory()->create());

        $this->artisan('vaults:delete', ['vault' => $vault->id, '--force' => true])
            ->expectsOutputToContain('Vault [Personal] deleted.')
            ->assertSuccessful();

        $this->assertModelMissing($vault);
        $this->assertModelMissing($backup);
        $this->assertModelMissing($file);
        Storage::disk('backups')->assertMissing($file->path);
        Storage::disk('backups')->assertExists($otherFile->path);
    }

    public function test_can_keep_the_backup_files(): void
    {
        Storage::fake('backups');
        $vault = Vault::factory()->create();
        $file = $this->storedFile($vault);

        $this->artisan('vaults:delete', ['vault' => $vault->id, '--force' => true, '--keep-files' => true])->assertSuccessful();

        $this->assertModelMissing($vault);
        Storage::disk('backups')->assertExists($file->path);
    }

    public function test_asks_for_confirmation_before_deleting(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);

        $this->artisan('vaults:delete', ['vault' => $vault->id])
            ->expectsConfirmation('Delete vault [Personal]?', 'yes')
            ->assertSuccessful();

        $this->assertModelMissing($vault);
    }

    public function test_deletes_nothing_when_not_confirmed(): void
    {
        Storage::fake('backups');
        $vault = Vault::factory()->create(['name' => 'Personal']);
        $file = $this->storedFile($vault);

        $this->artisan('vaults:delete', ['vault' => $vault->id])
            ->expectsConfirmation('Delete vault [Personal]?', 'no')
            ->expectsOutputToContain('Nothing was deleted.')
            ->assertFailed();

        $this->assertModelExists($vault);
        $this->assertModelExists($file);
        Storage::disk('backups')->assertExists($file->path);
    }

    public function test_requires_force_when_not_interactive(): void
    {
        $vault = Vault::factory()->create();

        $this->artisan('vaults:delete', ['vault' => $vault->id, '--no-interaction' => true])
            ->expectsOutputToContain('Pass --force to delete a vault without confirmation.')
            ->assertFailed();

        $this->assertModelExists($vault);
    }

    public function test_fails_for_a_vault_that_does_not_exist(): void
    {
        $this->artisan('vaults:delete', ['vault' => 999, '--force' => true])
            ->expectsOutputToContain('Vault [999] does not exist.')
            ->assertFailed();
    }

    /**
     * Create a backup file for the vault that exists on the backup disk.
     */
    private function storedFile(Vault $vault): BackupFile
    {
        $file = BackupFile::factory()
            ->for(Backup::factory()->for($vault)->succeeded())
            ->create(['disk' => 'backups']);

        Storage::disk('backups')->put($file->path, '{}');

        return $file;
    }
}
