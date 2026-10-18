<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsVaults;
use App\Models\Backup;
use App\Models\BackupFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

#[Signature('backups:list
    {vault : The ID of the vault to list the backups of}
    {--limit=20 : How many of the most recent backups to list}')]
#[Description("List a vault's most recent backups, their files and any errors")]
class ListBackups extends Command
{
    use FindsVaults;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vault = $this->findVault();

        if ($vault === null) {
            return self::FAILURE;
        }

        $backups = $vault->backups()
            ->with('files')
            ->latest()
            ->latest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($backups->isEmpty()) {
            $this->components->info("Vault [{$vault->name}] has no backups yet.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Status', 'Started', 'Completed', 'Files', 'Error'],
            $backups->map(fn (Backup $backup): array => [
                $backup->id,
                Str::headline($backup->status->value),
                $backup->started_at?->toDateTimeString() ?? '',
                $backup->completed_at?->toDateTimeString() ?? '',
                $backup->files
                    ->map(fn (BackupFile $file): string => "{$file->disk}:{$file->path} (".Number::fileSize($file->size_bytes, precision: 1).')')
                    ->implode("\n"),
                Str::limit((string) $backup->error, 100),
            ]),
        );

        return self::SUCCESS;
    }
}
