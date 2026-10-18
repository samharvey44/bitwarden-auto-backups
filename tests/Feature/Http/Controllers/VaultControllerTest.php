<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\BackupFrequency;
use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\BackupFile;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VaultControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('vaultPagesDataProvider')]
    public function test_guests_are_sent_to_the_login_page(string $method, string $url): void
    {
        $this->{$method}($url)->assertRedirect('/login');
    }

    public function test_lists_only_the_users_own_vaults_by_name(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $user = User::factory()->create();
        $work = Vault::factory()->for($user)->withServerUrl('https://vault.bitwarden.eu')->withJsonExport('zip-secret')->create([
            'name' => 'Work',
            'frequency' => BackupFrequency::Weekly,
            'last_backed_up_at' => '2026-10-10 09:00:00',
            'next_backup_at' => '2026-10-17 09:00:00',
        ]);
        $personal = Vault::factory()->for($user)->withServerUrl('https://vault.example.com')->create(['name' => 'Personal']);
        Vault::factory()->create(['name' => 'Someone else']);

        $this->actingAs($user)
            ->get('/vaults')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('vaults/Index')
                ->has('vaults', 2)
                ->where('vaults.0.id', $personal->id)
                ->where('vaults.0.server_label', 'vault.example.com')
                ->where('vaults.1', [
                    'id' => $work->id,
                    'name' => 'Work',
                    'server_url' => 'https://vault.bitwarden.eu',
                    'server_label' => 'Bitwarden cloud (EU)',
                    'formats' => ['json', 'encrypted_json'],
                    'has_json_export_password' => true,
                    'has_encrypted_json_export_password' => true,
                    'frequency' => 'weekly',
                    'frequency_label' => 'Weekly',
                    'retain_backups_count' => 30,
                    'last_backed_up_at' => '2026-10-10T09:00:00Z',
                    'next_backup_at' => '2026-10-17T09:00:00Z',
                ]));
    }

    public function test_never_sends_the_vaults_or_its_files_secrets_to_the_browser(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->withJsonExport('zip-secret-value')->create([
            'client_id' => 'user.client-id-value',
            'client_secret' => 'client-secret-value',
            'master_password' => 'master-password-value',
            'encrypted_json_export_password' => 'export-password-value',
        ]);

        BackupFile::factory()->for(Backup::factory()->for($vault)->succeeded())->create(['password' => 'file-password-value']);

        foreach (['/vaults', "/vaults/{$vault->id}", "/vaults/{$vault->id}/edit"] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertDontSee('client-id-value')
                ->assertDontSee('client-secret-value')
                ->assertDontSee('master-password-value')
                ->assertDontSee('export-password-value')
                ->assertDontSee('zip-secret-value')
                ->assertDontSee('file-password-value');
        }
    }

    public function test_shows_the_add_form_with_the_frequencies_and_cloud_servers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/vaults/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('vaults/Create')
                ->where('frequencies', [
                    ['value' => 'daily', 'label' => 'Daily'],
                    ['value' => 'weekly', 'label' => 'Weekly'],
                    ['value' => 'monthly', 'label' => 'Monthly'],
                ])
                ->where('cloudServerUrls', ['us' => 'https://vault.bitwarden.com', 'eu' => 'https://vault.bitwarden.eu']));
    }

    public function test_adds_a_vault_for_the_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/vaults', $this->validSettings([
            'formats' => ['encrypted_json', 'json'],
            'json_export_password' => 'zip-secret',
            'json_export_password_confirmation' => 'zip-secret',
        ]));

        $vault = $user->vaults()->sole();
        $response->assertRedirect("/vaults/{$vault->id}")->assertInertiaFlash('success', 'Personal was added. Check its credentials work before its first backup runs.');

        $this->assertSame('Personal', $vault->name);
        $this->assertSame('https://vault.bitwarden.com', $vault->server_url);
        $this->assertSame('user.1234', $vault->client_id);
        $this->assertSame('client-secret', $vault->client_secret);
        $this->assertSame('master-password', $vault->master_password);
        $this->assertSame('export-secret', $vault->encrypted_json_export_password);
        $this->assertSame('zip-secret', $vault->json_export_password);
        $this->assertSame(BackupFrequency::Weekly, $vault->frequency);
        $this->assertSame(14, $vault->retain_backups_count);
        $this->assertNull($vault->next_backup_at);
    }

    public function test_a_json_backup_without_a_zip_password_is_saved_as_plain_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vaults', $this->validSettings([
            'formats' => ['json'],
            'encrypted_json_export_password' => '',
            'encrypted_json_export_password_confirmation' => '',
        ]))->assertSessionHasNoErrors();

        $vault = $user->vaults()->sole();
        $this->assertTrue($vault->json_export_enabled);
        $this->assertNull($vault->json_export_password);
        $this->assertFalse($vault->encrypted_json_export_enabled);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidSettingsDataProvider')]
    public function test_rejects_invalid_settings(array $overrides, string $field, string $message): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/vaults', $this->validSettings($overrides))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('vaults', 0);
    }

    public function test_shows_the_users_vault(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal']);
        BackupFile::factory()->count(2)->sequence(fn () => ['backup_id' => Backup::factory()->for($vault)->succeeded()])->create();

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('vaults/Show')
                ->where('vault.name', 'Personal')
                ->where('backupFileCount', 2));
    }

    public function test_shows_the_vaults_backups_newest_first_with_their_files(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $this->travelTo('2026-10-12 12:00:00');
        $older = Backup::factory()->for($vault)->succeeded()->create();
        $file = BackupFile::factory()->for($older)->json('zip-secret')->create(['size_bytes' => 2048]);
        $this->travelTo('2026-10-13 12:00:00');
        $newer = Backup::factory()->for($vault)->failed()->create();
        Backup::factory()->succeeded()->create();

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('backupInProgress', false)
                ->has('backups.data', 2)
                ->where('backups.data.0.id', $newer->id)
                ->where('backups.data.0.status', 'failed')
                ->where('backups.data.0.status_label', 'Failed')
                ->where('backups.data.0.error', 'Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.')
                ->where('backups.data.0.files', [])
                ->where('backups.data.1.id', $older->id)
                ->where('backups.data.1.completed_at', '2026-10-12T12:00:00Z')
                ->where('backups.data.1.files', [[
                    'id' => $file->id,
                    'format' => 'json',
                    'format_label' => 'JSON',
                    'size_bytes' => 2048,
                    'is_encrypted' => true,
                    'download_url' => "http://localhost/vaults/{$vault->id}/backup-files/{$file->id}/download",
                ]]));
    }

    public function test_pages_through_the_backups_ten_at_a_time(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        Backup::factory()->for($vault)->succeeded()->count(12)->create();

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}?page=2")
            ->assertInertia(fn (Assert $page) => $page
                ->has('backups.data', 2)
                ->where('backups.current_page', 2)
                ->where('backups.last_page', 2));
    }

    public function test_reports_when_a_backup_is_in_progress(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        Backup::factory()->for($vault)->create(['status' => BackupStatus::Running]);

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}")
            ->assertInertia(fn (Assert $page) => $page->where('backupInProgress', true));
    }

    public function test_shows_the_edit_form_with_the_vaults_settings(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal']);

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->component('vaults/Edit')
                ->where('vault.id', $vault->id)
                ->has('frequencies', 3)
                ->has('cloudServerUrls'));
    }

    public function test_leaving_secrets_blank_keeps_their_current_values(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->withJsonExport('zip-secret')->create([
            'client_id' => 'user.1234',
            'client_secret' => 'client-secret',
            'master_password' => 'master-password',
            'encrypted_json_export_password' => 'export-secret',
        ]);

        $this->actingAs($user)
            ->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['name' => 'Family']))
            ->assertRedirect("/vaults/{$vault->id}")
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('success', 'Family was saved.');

        $vault->refresh();
        $this->assertSame('Family', $vault->name);
        $this->assertSame('user.1234', $vault->client_id);
        $this->assertSame('client-secret', $vault->client_secret);
        $this->assertSame('master-password', $vault->master_password);
        $this->assertSame('export-secret', $vault->encrypted_json_export_password);
        $this->assertSame('zip-secret', $vault->json_export_password);
    }

    public function test_new_credentials_replace_the_old_ones_and_suggest_checking_them(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal']);

        $this->actingAs($user)
            ->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['client_secret' => 'new-client-secret', 'master_password' => 'new-master-password']))
            ->assertInertiaFlash('success', 'Personal was saved. Check its new credentials work.');

        $vault->refresh();
        $this->assertSame('new-client-secret', $vault->client_secret);
        $this->assertSame('new-master-password', $vault->master_password);
    }

    public function test_a_new_export_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['encrypted_json_export_password' => 'export-secret']);

        $this->actingAs($user)
            ->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['encrypted_json_export_password' => 'new-export-secret']))
            ->assertSessionHasErrors(['encrypted_json_export_password' => 'The encrypted JSON export password field confirmation does not match.']);

        $this->assertSame('export-secret', $vault->refresh()->encrypted_json_export_password);
    }

    public function test_enabling_encrypted_json_needs_an_export_password(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->withoutEncryptedJsonExport()->withJsonExport()->create();

        $this->actingAs($user)
            ->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['formats' => ['json', 'encrypted_json']]))
            ->assertSessionHasErrors(['encrypted_json_export_password' => 'The encrypted JSON export password field is required.']);

        $this->assertFalse($vault->refresh()->encrypted_json_export_enabled);
    }

    public function test_can_remove_the_zip_password_to_save_plain_json(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->withJsonExport('zip-secret')->create();

        $this->actingAs($user)
            ->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['remove_json_export_password' => true]))
            ->assertSessionHasNoErrors();

        $this->assertNull($vault->refresh()->json_export_password);
    }

    public function test_changing_the_frequency_reschedules_from_the_last_backup(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create([
            'frequency' => BackupFrequency::Monthly,
            'last_backed_up_at' => '2026-10-01 09:00:00',
            'next_backup_at' => '2026-11-01 09:00:00',
        ]);

        $this->actingAs($user)->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['frequency' => 'weekly']))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-08 09:00:00', $vault->refresh()->next_backup_at->toDateTimeString());
    }

    public function test_deletes_the_vault_and_its_backup_files(): void
    {
        Storage::fake('backups');
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal']);
        $file = $this->storedFile($vault);

        $this->actingAs($user)
            ->delete("/vaults/{$vault->id}")
            ->assertRedirect('/vaults')
            ->assertInertiaFlash('success', 'Personal was deleted.');

        $this->assertModelMissing($vault);
        Storage::disk('backups')->assertMissing($file->path);
    }

    public function test_can_keep_the_backup_files_when_deleting(): void
    {
        Storage::fake('backups');
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $file = $this->storedFile($vault);

        $this->actingAs($user)->delete("/vaults/{$vault->id}", ['keep_files' => true])->assertRedirect('/vaults');

        $this->assertModelMissing($vault);
        Storage::disk('backups')->assertExists($file->path);
    }

    public function test_other_users_vaults_cannot_be_seen_changed_or_deleted(): void
    {
        $vault = Vault::factory()->create(['name' => 'Someone else']);
        $this->actingAs(User::factory()->create());

        $this->get("/vaults/{$vault->id}")->assertNotFound();
        $this->get("/vaults/{$vault->id}/edit")->assertNotFound();
        $this->patch("/vaults/{$vault->id}", $this->blankSecrets($vault, ['name' => 'Hijacked']))->assertNotFound();
        $this->delete("/vaults/{$vault->id}")->assertNotFound();

        $this->assertSame('Someone else', $vault->refresh()->name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function vaultPagesDataProvider(): array
    {
        return [
            'list' => ['get', '/vaults'],
            'add form' => ['get', '/vaults/create'],
            'add' => ['post', '/vaults'],
            'view' => ['get', '/vaults/1'],
            'edit form' => ['get', '/vaults/1/edit'],
            'update' => ['patch', '/vaults/1'],
            'delete' => ['delete', '/vaults/1'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidSettingsDataProvider(): array
    {
        return [
            'no formats' => [['formats' => []], 'formats', 'Choose at least one format to back up in.'],
            'unknown format' => [['formats' => ['csv']], 'formats.0', 'The selected format is invalid.'],
            'no server' => [['server_url' => ''], 'server_url', 'The server URL field is required.'],
            'server that is not a URL' => [['server_url' => 'vault.example.com'], 'server_url', 'The server URL field must be a valid URL.'],
            'client_secret pasted as the client_id' => [['client_id' => 'client-secret'], 'client_id', 'The API key client_id must start with "user.". Make sure it is the client_id, not the client_secret.'],
            'no master password' => [['master_password' => ''], 'master_password', 'The master password field is required.'],
            'no encrypted JSON export password' => [
                ['encrypted_json_export_password' => '', 'encrypted_json_export_password_confirmation' => ''],
                'encrypted_json_export_password',
                'The encrypted JSON export password field is required.',
            ],
            'unconfirmed export password' => [
                ['encrypted_json_export_password_confirmation' => 'export-secert'],
                'encrypted_json_export_password',
                'The encrypted JSON export password field confirmation does not match.',
            ],
            'unconfirmed ZIP password' => [
                ['formats' => ['json'], 'json_export_password' => 'zip-secret', 'json_export_password_confirmation' => 'zip-secert'],
                'json_export_password',
                'The ZIP password field confirmation does not match.',
            ],
            'unknown frequency' => [['frequency' => 'hourly'], 'frequency', 'The selected frequency is invalid.'],
            'keeping no backups' => [['retain_backups_count' => 0], 'retain_backups_count', 'The number of backups to keep field must be at least 1.'],
        ];
    }

    /**
     * Get settings for a new vault that pass validation.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSettings(array $overrides = []): array
    {
        return [
            'name' => 'Personal',
            'server_url' => 'https://vault.bitwarden.com',
            'client_id' => 'user.1234',
            'client_secret' => 'client-secret',
            'master_password' => 'master-password',
            'formats' => ['encrypted_json'],
            'encrypted_json_export_password' => 'export-secret',
            'encrypted_json_export_password_confirmation' => 'export-secret',
            'json_export_password' => '',
            'json_export_password_confirmation' => '',
            'frequency' => 'weekly',
            'retain_backups_count' => 14,
            ...$overrides,
        ];
    }

    /**
     * Get the edit form as the browser submits it for the vault, with every
     * secret left blank.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function blankSecrets(Vault $vault, array $overrides = []): array
    {
        return [
            'name' => $vault->name,
            'server_url' => $vault->server_url,
            'client_id' => '',
            'client_secret' => '',
            'master_password' => '',
            'formats' => array_map(fn ($format) => $format->value, $vault->enabledFormats()),
            'encrypted_json_export_password' => '',
            'encrypted_json_export_password_confirmation' => '',
            'json_export_password' => '',
            'json_export_password_confirmation' => '',
            'remove_json_export_password' => false,
            'frequency' => $vault->frequency->value,
            'retain_backups_count' => $vault->retain_backups_count,
            ...$overrides,
        ];
    }

    /**
     * Create a backup file for the vault that exists on the backup disk.
     */
    private function storedFile(Vault $vault): BackupFile
    {
        $file = BackupFile::factory()->for(Backup::factory()->for($vault)->succeeded())->create(['disk' => 'backups']);

        Storage::disk('backups')->put($file->path, '{}');

        return $file;
    }
}
