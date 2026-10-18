<?php

namespace App\Console\Commands;

use App\Models\Vault;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('backups:dispatch-due')]
#[Description('Queue a backup for every enabled vault whose next backup is due')]
class DispatchDueBackups extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        Vault::query()->dueForBackup()->chunkById(100, function (Collection $vaults) use (&$dispatched): void {
            $vaults->each(function (Vault $vault) use (&$dispatched): void {
                $vault->update(['next_backup_at' => $this->nextBackupTime($vault)]);

                $vault->queueBackup();

                $dispatched++;
            });
        });

        $this->components->info("Queued {$dispatched} vault backup(s).");

        return self::SUCCESS;
    }

    /**
     * Work out when the vault should next be backed up.
     */
    private function nextBackupTime(Vault $vault): CarbonImmutable
    {
        $now = now();

        $nextBackupAt = $vault->frequency->nextRunAfter($vault->next_backup_at ?? $now);

        return $nextBackupAt->isFuture() ? $nextBackupAt : $vault->frequency->nextRunAfter($now);
    }
}
