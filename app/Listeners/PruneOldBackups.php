<?php

namespace App\Listeners;

use App\Enums\BackupStatus;
use App\Events\BackupSucceeded;
use App\Models\Backup;
use App\Models\BackupFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

#[Tries(3)]
#[Backoff([60, 600])]
class PruneOldBackups implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Delete the vault's oldest successful backups, and their files, beyond its retention limit.
     */
    public function handle(BackupSucceeded $event): void
    {
        $vault = $event->backup->vault;

        $vault->backups()
            ->where('status', BackupStatus::Succeeded)
            ->with('files')
            ->latest('completed_at')
            ->latest('id')
            ->get()
            ->slice($vault->retain_backups_count)
            ->each(function (Backup $backup): void {
                $backup->files->each(fn (BackupFile $file) => Storage::disk($file->disk)->delete($file->path));

                $backup->delete();
            });
    }
}
