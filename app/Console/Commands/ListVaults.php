<?php

namespace App\Console\Commands;

use App\Enums\BackupFormat;
use App\Models\Vault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('vaults:list {--user= : Only list the vaults owned by the user with this email address}')]
#[Description('List the vaults being backed up and their schedules')]
class ListVaults extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vaults = Vault::query()
            ->with('user')
            ->when($this->option('user'), fn (Builder $query, string $email) => $query->whereRelation('user', 'email', $email))
            ->orderBy('id')
            ->get();

        if ($vaults->isEmpty()) {
            $this->components->info('There are no vaults yet. Add one with vaults:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Owner', 'Server', 'Formats', 'Schedule', 'Last backup', 'Next backup'],
            $vaults->map(fn (Vault $vault): array => [
                $vault->id,
                $vault->name,
                $vault->user->email,
                $vault->server_url,
                $this->formats($vault),
                "{$vault->frequency->label()}, keeping {$vault->retain_backups_count}",
                $vault->last_backed_up_at?->toDateTimeString() ?? 'Never',
                match (true) {
                    $vault->enabledFormats() === [] => 'Paused',
                    $vault->next_backup_at === null, $vault->next_backup_at->isPast() => 'Due now',
                    default => $vault->next_backup_at->toDateTimeString(),
                },
            ]),
        );

        return self::SUCCESS;
    }

    /**
     * Describe the formats the vault is backed up in.
     */
    private function formats(Vault $vault): string
    {
        $formats = array_map(fn (BackupFormat $format): string => match (true) {
            $format === BackupFormat::Json && filled($vault->json_export_password) => 'JSON (ZIP)',
            $format === BackupFormat::Json => 'JSON (unencrypted)',
            default => $format->label(),
        }, $vault->enabledFormats());

        return $formats === [] ? 'None' : implode(', ', $formats);
    }
}
