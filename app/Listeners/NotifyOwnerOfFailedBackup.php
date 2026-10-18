<?php

namespace App\Listeners;

use App\Enums\BackupStatus;
use App\Events\BackupFailed;
use App\Notifications\VaultBackupFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;

#[Tries(3)]
#[Backoff([60, 600])]
class NotifyOwnerOfFailedBackup implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notify the vault's owner when a backup fails, unless the backup before it
     * also failed, so a vault that keeps failing sends one notification until
     * one of its backups succeeds again.
     */
    public function handle(BackupFailed $event): void
    {
        $backup = $event->backup;
        $vault = $backup->vault;

        $previousStatus = $vault->backups()
            ->whereIn('status', [BackupStatus::Succeeded, BackupStatus::Failed])
            ->where('id', '<', $backup->id)
            ->latest('id')
            ->value('status');

        if ($previousStatus === BackupStatus::Failed) {
            return;
        }

        $vault->user->notify(new VaultBackupFailed($backup));
    }
}
