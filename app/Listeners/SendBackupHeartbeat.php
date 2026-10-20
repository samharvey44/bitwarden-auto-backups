<?php

namespace App\Listeners;

use App\Events\BackupSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

#[Tries(3)]
#[Backoff([60, 600])]
class SendBackupHeartbeat implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Ping the configured heartbeat URL after a successful backup, so a
     * monitoring service can alert when backups stop, whatever the reason. A
     * failed ping throws, so it is retried.
     */
    public function handle(BackupSucceeded $event): void
    {
        $url = config('backups.heartbeat_url');

        if (blank($url)) {
            return;
        }

        Http::connectTimeout(5)->timeout(10)->get($url)->throw();
    }
}
