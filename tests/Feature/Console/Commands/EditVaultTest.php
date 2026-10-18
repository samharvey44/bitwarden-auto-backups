<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BackupFrequency;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class EditVaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_changes_only_the_settings_given_as_options(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal', 'retain_backups_count' => 30]);
        $original = $vault->replicate();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--name' => 'Family', '--retain' => '10', '--no-interaction' => true])
            ->expectsOutputToContain('Vault [Family] updated.')
            ->doesntExpectOutputToContain('vaults:verify')
            ->assertSuccessful();

        $vault->refresh();
        $this->assertSame('Family', $vault->name);
        $this->assertSame(10, $vault->retain_backups_count);
        $this->assertSame($original->client_secret, $vault->client_secret);
        $this->assertSame($original->master_password, $vault->master_password);
        $this->assertSame($original->encrypted_json_export_password, $vault->encrypted_json_export_password);
        $this->assertSame(BackupFrequency::Daily, $vault->frequency);
    }

    public function test_changing_the_frequency_reschedules_from_the_last_backup(): void
    {
        $vault = Vault::factory()->create([
            'frequency' => BackupFrequency::Monthly,
            'last_backed_up_at' => '2026-10-01 09:00:00',
            'next_backup_at' => '2026-11-01 09:00:00',
        ]);

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--frequency' => 'weekly', '--no-interaction' => true])->assertSuccessful();

        $vault->refresh();
        $this->assertSame(BackupFrequency::Weekly, $vault->frequency);
        $this->assertSame('2026-10-08 09:00:00', $vault->next_backup_at->toDateTimeString());
    }

    public function test_replaces_secrets_set_in_the_environment_and_suggests_verifying_them(): void
    {
        $vault = Vault::factory()->create();
        $this->withProcessEnvironment(['VAULT_CLIENT_SECRET' => 'new-client-secret', 'VAULT_MASTER_PASSWORD' => 'new-master-password']);

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--no-interaction' => true])
            ->expectsOutputToContain("Run [vaults:verify {$vault->id}]")
            ->assertSuccessful();

        $vault->refresh();
        $this->assertSame('new-client-secret', $vault->client_secret);
        $this->assertSame('new-master-password', $vault->master_password);
    }

    public function test_switching_formats_clears_the_password_of_the_disabled_format(): void
    {
        $vault = Vault::factory()->create();
        $this->withProcessEnvironment(['VAULT_JSON_EXPORT_PASSWORD' => 'zip-secret']);

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--format' => ['json'], '--no-interaction' => true])->assertSuccessful();

        $vault->refresh();
        $this->assertTrue($vault->json_export_enabled);
        $this->assertSame('zip-secret', $vault->json_export_password);
        $this->assertFalse($vault->encrypted_json_export_enabled);
        $this->assertNull($vault->encrypted_json_export_password);
    }

    public function test_enabling_encrypted_json_needs_an_export_password(): void
    {
        $vault = Vault::factory()->withoutEncryptedJsonExport()->withJsonExport()->create();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--format' => ['json', 'encrypted_json'], '--no-interaction' => true])
            ->expectsOutputToContain('Encrypted JSON backups need an export password.')
            ->assertFailed();

        $this->assertFalse($vault->refresh()->encrypted_json_export_enabled);
    }

    public function test_can_stop_zipping_json_backups(): void
    {
        $vault = Vault::factory()->withJsonExport('zip-secret')->create();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--without-json-export-password' => true, '--no-interaction' => true])->assertSuccessful();

        $vault->refresh();
        $this->assertTrue($vault->json_export_enabled);
        $this->assertNull($vault->json_export_password);
    }

    public function test_changing_the_server_url_suggests_verifying_the_credentials(): void
    {
        $vault = Vault::factory()->create();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--server-url' => 'https://vault.bitwarden.eu', '--no-interaction' => true])
            ->expectsOutputToContain("Run [vaults:verify {$vault->id}]")
            ->assertSuccessful();

        $this->assertSame('https://vault.bitwarden.eu', $vault->refresh()->server_url);
    }

    public function test_the_server_url_cannot_be_removed(): void
    {
        $vault = Vault::factory()->withServerUrl('https://vault.bitwarden.eu')->create();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--server-url' => '', '--no-interaction' => true])
            ->expectsOutputToContain('The server URL is required.')
            ->assertFailed();

        $this->assertSame('https://vault.bitwarden.eu', $vault->refresh()->server_url);
    }

    public function test_a_vault_with_no_formats_can_still_be_edited(): void
    {
        $vault = Vault::factory()->withoutExports()->create();

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--name' => 'Paused', '--no-interaction' => true])->assertSuccessful();

        $this->assertSame('Paused', $vault->refresh()->name);
    }

    public function test_fails_without_prompting_when_nothing_is_changed(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);

        $this->artisan('vaults:edit', ['vault' => $vault->id, '--no-interaction' => true])
            ->expectsOutputToContain('Nothing to change.')
            ->assertFailed();
    }

    public function test_fails_for_a_vault_that_does_not_exist(): void
    {
        $this->artisan('vaults:edit', ['vault' => 999, '--name' => 'Personal'])
            ->expectsOutputToContain('Vault [999] does not exist.')
            ->assertFailed();
    }

    public function test_prompts_for_the_chosen_settings_only(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal', 'frequency' => BackupFrequency::Daily, 'retain_backups_count' => 30]);

        $this->artisan('vaults:edit', ['vault' => $vault->id])
            ->expectsQuestion('What would you like to change?', ['name', 'schedule'])
            ->expectsQuestion('What should the vault be called?', 'Family')
            ->expectsQuestion('How often should the vault be backed up?', 'monthly')
            ->expectsQuestion('How many successful backups should be kept?', '12')
            ->assertSuccessful();

        $vault->refresh();
        $this->assertSame('Family', $vault->name);
        $this->assertSame(BackupFrequency::Monthly, $vault->frequency);
        $this->assertSame(12, $vault->retain_backups_count);
    }

    public function test_adding_a_format_asks_for_its_password_and_keeps_existing_ones_unless_changed(): void
    {
        $vault = Vault::factory()->create(['encrypted_json_export_password' => 'export-secret']);

        $this->artisan('vaults:edit', ['vault' => $vault->id])
            ->expectsQuestion('What would you like to change?', ['exports'])
            ->expectsQuestion('Which formats should backups be saved in?', ['encrypted_json', 'json'])
            ->expectsConfirmation('Change the Encrypted JSON export password?', 'no')
            ->expectsQuestion('ZIP password for JSON backups', 'zip-secret')
            ->expectsQuestion('Enter the password again to confirm', 'zip-secret')
            ->assertSuccessful();

        $vault->refresh();
        $this->assertSame('export-secret', $vault->encrypted_json_export_password);
        $this->assertTrue($vault->json_export_enabled);
        $this->assertSame('zip-secret', $vault->json_export_password);
    }

    public function test_can_check_new_credentials_once_saved(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyCredentials')->once();
        });

        $this->artisan('vaults:edit', ['vault' => $vault->id])
            ->expectsQuestion('What would you like to change?', ['credentials'])
            ->expectsQuestion('API key client_id', 'user.5678')
            ->expectsQuestion('API key client_secret', 'new-client-secret')
            ->expectsQuestion('Master password', 'new-master-password')
            ->expectsConfirmation('Check that the new credentials work now?', 'yes')
            ->expectsOutputToContain('The credentials for vault [Personal] work.')
            ->assertSuccessful();

        $this->assertSame('user.5678', $vault->refresh()->client_id);
    }
}
