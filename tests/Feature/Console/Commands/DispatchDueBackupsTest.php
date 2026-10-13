<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BackupFrequency;
use App\Enums\BackupStatus;
use App\Jobs\BackupVault;
use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DispatchDueBackupsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_queues_a_pending_backup_for_each_due_vault_only(): void
    {
        $this->travelTo('2026-10-13 12:00:00');

        $due = Vault::factory()->create(['next_backup_at' => '2026-10-13 11:00:00']);
        $neverBackedUp = Vault::factory()->create(['next_backup_at' => null]);
        $jsonOnly = Vault::factory()->withoutEncryptedJsonExport()->withJsonExport()->create(['next_backup_at' => '2026-10-13 11:00:00']);
        $notYetDue = Vault::factory()->create(['next_backup_at' => '2026-10-13 13:00:00']);
        $withoutExports = Vault::factory()->withoutExports()->create(['next_backup_at' => '2026-10-13 11:00:00']);

        Queue::fake([BackupVault::class]);

        $this->artisan('backups:dispatch-due')->assertSuccessful();

        $this->assertSame(
            [$due->id, $neverBackedUp->id, $jsonOnly->id],
            Queue::pushed(BackupVault::class)->map(fn (BackupVault $job): int => $job->backup->vault_id)->sort()->values()->all(),
        );

        $this->assertDatabaseHas('backups', ['vault_id' => $due->id, 'status' => BackupStatus::Pending]);
        $this->assertDatabaseHas('backups', ['vault_id' => $neverBackedUp->id, 'status' => BackupStatus::Pending]);
        $this->assertDatabaseMissing('backups', ['vault_id' => $notYetDue->id]);
        $this->assertDatabaseMissing('backups', ['vault_id' => $withoutExports->id]);
    }

    #[DataProvider('nextBackupTimes')]
    public function test_schedules_the_next_backup(BackupFrequency $frequency, ?string $nextBackupAt, string $expected): void
    {
        $this->travelTo('2026-10-13 12:00:00');

        $vault = Vault::factory()->create(['frequency' => $frequency, 'next_backup_at' => $nextBackupAt]);

        Queue::fake([BackupVault::class]);

        $this->artisan('backups:dispatch-due')->assertSuccessful();

        $this->assertSame($expected, $vault->refresh()->next_backup_at->toDateTimeString());
    }

    /**
     * @return array<string, array{BackupFrequency, string|null, string}>
     */
    public static function nextBackupTimes(): array
    {
        return [
            'first backup schedules one period from now' => [BackupFrequency::Daily, null, '2026-10-14 12:00:00'],
            'keeps the original time of day' => [BackupFrequency::Daily, '2026-10-13 11:00:00', '2026-10-14 11:00:00'],
            'weekly schedules seven days on' => [BackupFrequency::Weekly, '2026-10-13 11:00:00', '2026-10-20 11:00:00'],
            'missed runs restart from now without a backlog' => [BackupFrequency::Daily, '2026-10-10 09:00:00', '2026-10-14 12:00:00'],
        ];
    }
}
