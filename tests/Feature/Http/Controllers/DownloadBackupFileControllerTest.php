<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupFile;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadBackupFileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_downloads_the_file_named_after_its_vault(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal Vault']);
        $file = $this->storedFile($vault, '1/2026-10-18_162515-1.encrypted.json', '{"encrypted":true}');

        $response = $this->actingAs($user)->get("/vaults/{$vault->id}/backup-files/{$file->id}/download");

        $response->assertOk()->assertDownload('personal-vault-2026-10-18_162515-1.encrypted.json');
        $this->assertSame('{"encrypted":true}', $response->streamedContent());
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $vault = Vault::factory()->create();
        $file = $this->storedFile($vault);

        $this->get("/vaults/{$vault->id}/backup-files/{$file->id}/download")->assertRedirect('/login');
    }

    public function test_cannot_download_another_users_backup(): void
    {
        $vault = Vault::factory()->create();
        $file = $this->storedFile($vault);

        $this->actingAs(User::factory()->create())
            ->get("/vaults/{$vault->id}/backup-files/{$file->id}/download")
            ->assertNotFound();
    }

    public function test_cannot_download_another_users_backup_through_your_own_vault(): void
    {
        $user = User::factory()->create();
        $ownVault = Vault::factory()->for($user)->create();
        $otherFile = $this->storedFile(Vault::factory()->create());

        $this->actingAs($user)
            ->get("/vaults/{$ownVault->id}/backup-files/{$otherFile->id}/download")
            ->assertNotFound();
    }

    public function test_reports_a_file_missing_from_its_disk_as_not_found(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $file = BackupFile::factory()->for(Backup::factory()->for($vault)->succeeded())->create(['disk' => 'backups']);

        $this->actingAs($user)
            ->get("/vaults/{$vault->id}/backup-files/{$file->id}/download")
            ->assertNotFound();
    }

    /**
     * Create a backup file for the vault that exists on the backup disk.
     */
    private function storedFile(Vault $vault, ?string $path = null, string $contents = '{}'): BackupFile
    {
        $file = BackupFile::factory()
            ->for(Backup::factory()->for($vault)->succeeded())
            ->create(['disk' => 'backups', ...($path === null ? [] : ['path' => $path])]);

        Storage::disk('backups')->put($file->path, $contents);

        return $file;
    }
}
