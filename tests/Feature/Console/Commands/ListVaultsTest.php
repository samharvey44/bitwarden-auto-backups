<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BackupFrequency;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ListVaultsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_every_vault_with_its_schedule(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $owner = User::factory()->create(['email' => 'sam@example.com']);
        $backedUp = Vault::factory()->for($owner)->withJsonExport('zip-secret')->create([
            'name' => 'Personal',
            'frequency' => BackupFrequency::Weekly,
            'retain_backups_count' => 4,
            'last_backed_up_at' => '2026-10-10 09:00:00',
            'next_backup_at' => '2026-10-17 09:00:00',
        ]);
        $new = Vault::factory()->for($owner)->withServerUrl('https://vault.bitwarden.eu')->withoutEncryptedJsonExport()->withJsonExport()->create(['name' => 'Work']);
        $paused = Vault::factory()->for($owner)->withoutExports()->create(['name' => 'Old']);

        $this->artisan('vaults:list')
            ->expectsTable(
                ['ID', 'Name', 'Owner', 'Server', 'Formats', 'Schedule', 'Last backup', 'Next backup'],
                [
                    [$backedUp->id, 'Personal', 'sam@example.com', 'https://vault.bitwarden.com', 'JSON (ZIP), Encrypted JSON', 'Weekly, keeping 4', '2026-10-10 09:00:00', '2026-10-17 09:00:00'],
                    [$new->id, 'Work', 'sam@example.com', 'https://vault.bitwarden.eu', 'JSON (unencrypted)', 'Daily, keeping 30', 'Never', 'Due now'],
                    [$paused->id, 'Old', 'sam@example.com', 'https://vault.bitwarden.com', 'None', 'Daily, keeping 30', 'Never', 'Paused'],
                ],
            )
            ->assertSuccessful();
    }

    public function test_can_list_only_one_users_vaults(): void
    {
        Vault::factory()->for(User::factory()->create(['email' => 'sam@example.com']))->create(['name' => 'Personal']);
        Vault::factory()->for(User::factory()->create(['email' => 'alex@example.com']))->create(['name' => 'Alex']);

        $this->artisan('vaults:list', ['--user' => 'sam@example.com'])
            ->expectsOutputToContain('Personal')
            ->doesntExpectOutputToContain('Alex')
            ->assertSuccessful();
    }

    public function test_says_when_there_are_no_vaults(): void
    {
        $this->artisan('vaults:list')
            ->expectsOutputToContain('There are no vaults yet. Add one with vaults:create.')
            ->assertSuccessful();
    }
}
