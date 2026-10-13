<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BackupStatus;
use App\Jobs\BackupVault;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunVaultBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_queues_an_immediate_backup_of_the_vault(): void
    {
        $vault = Vault::factory()->create(['next_backup_at' => '2026-12-01 00:00:00']);
        Queue::fake([BackupVault::class]);

        $this->artisan('backups:run', ['vault' => $vault->id])->assertSuccessful();

        $backup = $vault->backups()->sole();
        $this->assertSame(BackupStatus::Pending, $backup->status);

        Queue::assertPushed(BackupVault::class, fn (BackupVault $job): bool => $job->backup->is($backup));

        $this->assertSame('2026-12-01 00:00:00', $vault->refresh()->next_backup_at->toDateTimeString());
    }

    public function test_fails_for_a_vault_that_does_not_exist(): void
    {
        Queue::fake([BackupVault::class]);

        $this->artisan('backups:run', ['vault' => 999])
            ->expectsOutputToContain('Vault [999] does not exist.')
            ->assertFailed();

        Queue::assertNotPushed(BackupVault::class);
        $this->assertDatabaseCount('backups', 0);
    }

    public function test_fails_for_a_vault_with_no_backup_formats_enabled(): void
    {
        $vault = Vault::factory()->withoutExports()->create(['name' => 'Personal']);
        Queue::fake([BackupVault::class]);

        $this->artisan('backups:run', ['vault' => $vault->id])
            ->expectsOutputToContain('Vault [Personal] has no backup formats enabled.')
            ->assertFailed();

        Queue::assertNotPushed(BackupVault::class);
        $this->assertDatabaseCount('backups', 0);
    }
}
