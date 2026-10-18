<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Backup;
use App\Models\BackupFile;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListBackupsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_the_vaults_backups_newest_first_with_their_files_and_errors(): void
    {
        $vault = Vault::factory()->create();
        $this->travelTo('2026-10-12 12:00:00');
        $succeeded = Backup::factory()->for($vault)->succeeded()->create();
        BackupFile::factory()->for($succeeded)->create(['disk' => 'backups', 'path' => '1/a.encrypted.json', 'size_bytes' => 2048]);
        BackupFile::factory()->for($succeeded)->json('zip-secret')->create(['disk' => 'backups', 'path' => '1/a.zip', 'size_bytes' => 1024]);
        $this->travelTo('2026-10-13 12:00:00');
        $failed = Backup::factory()->for($vault)->failed()->create();
        Backup::factory()->succeeded()->create();

        $this->artisan('backups:list', ['vault' => $vault->id])
            ->expectsTable(
                ['ID', 'Status', 'Started', 'Completed', 'Files', 'Error'],
                [
                    [$failed->id, 'Failed', '2026-10-13 12:00:00', '2026-10-13 12:00:00', '', 'Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.'],
                    [$succeeded->id, 'Succeeded', '2026-10-12 12:00:00', '2026-10-12 12:00:00', "backups:1/a.encrypted.json (2.0 KB)\nbackups:1/a.zip (1.0 KB)", ''],
                ],
            )
            ->assertSuccessful();
    }

    public function test_limits_how_many_backups_are_listed(): void
    {
        $vault = Vault::factory()->create();
        $this->travelTo('2026-10-12 12:00:00');
        $older = Backup::factory()->for($vault)->succeeded()->create();
        $this->travelTo('2026-10-13 12:00:00');
        $newer = Backup::factory()->for($vault)->succeeded()->create();

        $this->artisan('backups:list', ['vault' => $vault->id, '--limit' => 1])
            ->expectsTable(
                ['ID', 'Status', 'Started', 'Completed', 'Files', 'Error'],
                [[$newer->id, 'Succeeded', '2026-10-13 12:00:00', '2026-10-13 12:00:00', '', '']],
            )
            ->assertSuccessful();

        $this->assertModelExists($older);
    }

    public function test_says_when_the_vault_has_no_backups(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);

        $this->artisan('backups:list', ['vault' => $vault->id])
            ->expectsOutputToContain('Vault [Personal] has no backups yet.')
            ->assertSuccessful();
    }

    public function test_fails_for_a_vault_that_does_not_exist(): void
    {
        $this->artisan('backups:list', ['vault' => 999])
            ->expectsOutputToContain('Vault [999] does not exist.')
            ->assertFailed();
    }
}
