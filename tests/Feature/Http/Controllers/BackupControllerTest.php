<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\BackupStatus;
use App\Jobs\BackupVault;
use App\Models\Backup;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $vault = Vault::factory()->create();

        $this->post("/vaults/{$vault->id}/backups")->assertRedirect('/login');
    }

    public function test_queues_a_backup_of_the_vault_now(): void
    {
        Queue::fake([BackupVault::class]);
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal', 'next_backup_at' => '2026-12-01 00:00:00']);

        $this->actingAs($user)
            ->post("/vaults/{$vault->id}/backups")
            ->assertRedirect("/vaults/{$vault->id}")
            ->assertInertiaFlash('success', 'A backup of Personal was queued. This page updates as it runs.');

        $backup = $vault->backups()->sole();
        $this->assertSame(BackupStatus::Pending, $backup->status);
        Queue::assertPushed(BackupVault::class, fn (BackupVault $job): bool => $job->backup->is($backup));
        $this->assertSame('2026-12-01 00:00:00', $vault->refresh()->next_backup_at->toDateTimeString());
    }

    #[DataProvider('inProgressStatusesDataProvider')]
    public function test_does_not_queue_a_second_backup_while_one_is_in_progress(BackupStatus $status): void
    {
        Queue::fake([BackupVault::class]);
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        Backup::factory()->for($vault)->create(['status' => $status]);

        $this->actingAs($user)
            ->post("/vaults/{$vault->id}/backups")
            ->assertSessionHasErrors(['backup' => 'A backup of this vault is already queued or running.']);

        Queue::assertNothingPushed();
        $this->assertSame(1, $vault->backups()->count());
    }

    public function test_does_not_queue_a_backup_of_a_vault_with_no_formats(): void
    {
        Queue::fake([BackupVault::class]);
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->withoutExports()->create();

        $this->actingAs($user)
            ->post("/vaults/{$vault->id}/backups")
            ->assertSessionHasErrors(['backup' => 'Choose at least one backup format before backing up.']);

        Queue::assertNothingPushed();
    }

    public function test_cannot_back_up_another_users_vault(): void
    {
        Queue::fake([BackupVault::class]);
        $vault = Vault::factory()->create();

        $this->actingAs(User::factory()->create())->post("/vaults/{$vault->id}/backups")->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('backups', 0);
    }

    /**
     * @return array<string, array{BackupStatus}>
     */
    public static function inProgressStatusesDataProvider(): array
    {
        return [
            'queued' => [BackupStatus::Pending],
            'running' => [BackupStatus::Running],
        ];
    }
}
