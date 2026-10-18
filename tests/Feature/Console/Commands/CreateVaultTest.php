<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BackupFrequency;
use App\Models\User;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateVaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prompts_for_every_setting_and_creates_the_vault(): void
    {
        $owner = User::factory()->create(['email' => 'sam@example.com']);
        User::factory()->create(['email' => 'alex@example.com']);

        $this->artisan('vaults:create')
            ->expectsChoice('Which user owns the vault?', 'sam@example.com', ['alex@example.com' => 'alex@example.com', 'sam@example.com' => 'sam@example.com'])
            ->expectsQuestion('What should the vault be called?', 'Personal')
            ->expectsQuestion('Which Bitwarden server is the account on?', 'eu')
            ->expectsQuestion('API key client_id', 'user.1234')
            ->expectsQuestion('API key client_secret', 'client-secret')
            ->expectsQuestion('Master password', 'master-password')
            ->expectsQuestion('Which formats should backups be saved in?', ['encrypted_json', 'json'])
            ->expectsQuestion('Encrypted JSON export password', 'export-secret')
            ->expectsQuestion('Enter the password again to confirm', 'export-secret')
            ->expectsQuestion('ZIP password for JSON backups', 'zip-secret')
            ->expectsQuestion('Enter the password again to confirm', 'zip-secret')
            ->expectsQuestion('How often should the vault be backed up?', 'weekly')
            ->expectsQuestion('How many successful backups should be kept?', '14')
            ->expectsConfirmation('Check that the credentials work now?', 'no')
            ->expectsOutputToContain('Vault [Personal] created')
            ->assertSuccessful();

        $vault = Vault::sole();
        $this->assertTrue($vault->user->is($owner));
        $this->assertSame('Personal', $vault->name);
        $this->assertSame('https://vault.bitwarden.eu', $vault->server_url);
        $this->assertSame('user.1234', $vault->client_id);
        $this->assertSame('client-secret', $vault->client_secret);
        $this->assertSame('master-password', $vault->master_password);
        $this->assertTrue($vault->encrypted_json_export_enabled);
        $this->assertSame('export-secret', $vault->encrypted_json_export_password);
        $this->assertTrue($vault->json_export_enabled);
        $this->assertSame('zip-secret', $vault->json_export_password);
        $this->assertSame(BackupFrequency::Weekly, $vault->frequency);
        $this->assertSame(14, $vault->retain_backups_count);
        $this->assertNull($vault->next_backup_at);
    }

    public function test_us_cloud_is_stored_by_its_url_and_a_blank_zip_password_saves_plain_json(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Personal'])
            ->expectsQuestion('Which Bitwarden server is the account on?', 'us')
            ->expectsQuestion('API key client_id', 'user.1234')
            ->expectsQuestion('API key client_secret', 'client-secret')
            ->expectsQuestion('Master password', 'master-password')
            ->expectsQuestion('Which formats should backups be saved in?', ['json'])
            ->expectsQuestion('ZIP password for JSON backups', '')
            ->expectsQuestion('How often should the vault be backed up?', 'daily')
            ->expectsQuestion('How many successful backups should be kept?', '30')
            ->expectsConfirmation('Check that the credentials work now?', 'no')
            ->assertSuccessful();

        $vault = Vault::sole();
        $this->assertSame('https://vault.bitwarden.com', $vault->server_url);
        $this->assertTrue($vault->json_export_enabled);
        $this->assertNull($vault->json_export_password);
        $this->assertFalse($vault->encrypted_json_export_enabled);
        $this->assertNull($vault->encrypted_json_export_password);
    }

    public function test_asks_for_the_url_of_a_self_hosted_server(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment($this->secrets());

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Work', '--format' => ['encrypted_json'], '--frequency' => 'daily', '--retain' => '30'])
            ->expectsQuestion('Which Bitwarden server is the account on?', 'self-hosted')
            ->expectsQuestion('What is the server URL?', 'https://vault.example.com')
            ->expectsConfirmation('Check that the credentials work now?', 'no')
            ->assertSuccessful();

        $this->assertSame('https://vault.example.com', Vault::sole()->server_url);
    }

    public function test_only_prompts_for_secrets_missing_from_the_environment(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment(['VAULT_CLIENT_ID' => 'user.1234', 'VAULT_CLIENT_SECRET' => 'client-secret']);

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Personal', '--server-url' => 'https://vault.bitwarden.com', '--format' => ['encrypted_json'], '--frequency' => 'daily', '--retain' => '30'])
            ->expectsQuestion('Master password', 'master-password')
            ->expectsQuestion('Encrypted JSON export password', 'export-secret')
            ->expectsQuestion('Enter the password again to confirm', 'export-secret')
            ->expectsConfirmation('Check that the credentials work now?', 'no')
            ->assertSuccessful();

        $vault = Vault::sole();
        $this->assertSame('user.1234', $vault->client_id);
        $this->assertSame('master-password', $vault->master_password);
    }

    public function test_rejects_an_export_password_that_is_not_confirmed(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment(['VAULT_CLIENT_ID' => 'user.1234', 'VAULT_CLIENT_SECRET' => 'client-secret', 'VAULT_MASTER_PASSWORD' => 'master-password']);

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Personal', '--server-url' => 'https://vault.bitwarden.com', '--format' => ['encrypted_json']])
            ->expectsQuestion('Encrypted JSON export password', 'export-secret')
            ->expectsQuestion('Enter the password again to confirm', 'export-secert')
            ->expectsOutputToContain('The passwords do not match.')
            ->assertFailed();

        $this->assertDatabaseCount('vaults', 0);
    }

    public function test_creates_the_vault_without_prompting_from_options_and_environment_variables(): void
    {
        $owner = User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment($this->secrets());

        $this->artisan('vaults:create', [
            '--user' => 'sam@example.com',
            '--name' => 'Personal',
            '--server-url' => 'https://vault.example.com',
            '--format' => ['encrypted_json', 'json'],
            '--frequency' => 'monthly',
            '--retain' => '6',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Run [vaults:verify')
            ->assertSuccessful();

        $vault = Vault::sole();
        $this->assertTrue($vault->user->is($owner));
        $this->assertSame('https://vault.example.com', $vault->server_url);
        $this->assertSame('user.1234', $vault->client_id);
        $this->assertSame('client-secret', $vault->client_secret);
        $this->assertSame('master-password', $vault->master_password);
        $this->assertSame('export-secret', $vault->encrypted_json_export_password);
        $this->assertSame('zip-secret', $vault->json_export_password);
        $this->assertSame(BackupFrequency::Monthly, $vault->frequency);
        $this->assertSame(6, $vault->retain_backups_count);
    }

    public function test_defaults_to_daily_encrypted_json_backups_keeping_30_without_prompting(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment($this->secrets());

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Personal', '--server-url' => 'https://vault.bitwarden.eu', '--no-interaction' => true])->assertSuccessful();

        $vault = Vault::sole();
        $this->assertSame('https://vault.bitwarden.eu', $vault->server_url);
        $this->assertTrue($vault->encrypted_json_export_enabled);
        $this->assertFalse($vault->json_export_enabled);
        $this->assertSame(BackupFrequency::Daily, $vault->frequency);
        $this->assertSame(30, $vault->retain_backups_count);
    }

    public function test_reports_every_missing_setting_without_prompting(): void
    {
        $this->artisan('vaults:create', ['--no-interaction' => true])
            ->expectsOutputToContain('The owner is required. Pass their email address with --user.')
            ->expectsOutputToContain('The name is required. Pass it with --name.')
            ->expectsOutputToContain('The server URL is required. Pass it with --server-url, such as https://vault.bitwarden.com for the US cloud or https://vault.bitwarden.eu for the EU cloud.')
            ->expectsOutputToContain('Set it in the VAULT_CLIENT_ID environment variable.')
            ->expectsOutputToContain('Set it in the VAULT_CLIENT_SECRET environment variable.')
            ->expectsOutputToContain('Set it in the VAULT_MASTER_PASSWORD environment variable.')
            ->expectsOutputToContain('Set it in the VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD environment variable.')
            ->assertFailed();

        $this->assertDatabaseCount('vaults', 0);
    }

    public function test_rejects_invalid_options(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment([...$this->secrets(), 'VAULT_CLIENT_ID' => 'client-secret-pasted-by-mistake']);

        $this->artisan('vaults:create', [
            '--user' => 'sam@example.com',
            '--name' => 'Personal',
            '--server-url' => 'vault.example.com',
            '--format' => ['zip'],
            '--frequency' => 'hourly',
            '--retain' => '0',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('The server URL field must be a valid URL.')
            ->expectsOutputToContain('The API key client_id must start with "user.".')
            ->expectsOutputToContain('The selected format is invalid.')
            ->expectsOutputToContain('The selected frequency is invalid.')
            ->expectsOutputToContain('The number of backups to keep field must be at least 1.')
            ->assertFailed();

        $this->assertDatabaseCount('vaults', 0);
    }

    public function test_fails_for_an_owner_that_does_not_exist(): void
    {
        $this->withProcessEnvironment($this->secrets());

        $this->artisan('vaults:create', ['--user' => 'nobody@example.com', '--name' => 'Personal', '--no-interaction' => true])
            ->expectsOutputToContain('No user has the email address [nobody@example.com]. Create them with users:create first.')
            ->assertFailed();

        $this->assertDatabaseCount('vaults', 0);
    }

    public function test_fails_before_prompting_when_there_are_no_users(): void
    {
        $this->artisan('vaults:create')
            ->expectsOutputToContain('No users exist yet. Create one with users:create first.')
            ->assertFailed();
    }

    public function test_can_check_the_credentials_once_created(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment($this->secrets());
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyCredentials')->once();
        });

        $this->artisan('vaults:create', ['--user' => 'sam@example.com', '--name' => 'Personal', '--server-url' => 'https://vault.bitwarden.com', '--format' => ['encrypted_json'], '--frequency' => 'daily', '--retain' => '30'])
            ->expectsConfirmation('Check that the credentials work now?', 'yes')
            ->expectsOutputToContain('The credentials for vault [Personal] work.')
            ->assertSuccessful();
    }

    /**
     * Get every vault secret, keyed by its environment variable.
     *
     * @return array<string, string>
     */
    private function secrets(): array
    {
        return [
            'VAULT_CLIENT_ID' => 'user.1234',
            'VAULT_CLIENT_SECRET' => 'client-secret',
            'VAULT_MASTER_PASSWORD' => 'master-password',
            'VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD' => 'export-secret',
            'VAULT_JSON_EXPORT_PASSWORD' => 'zip-secret',
        ];
    }
}
