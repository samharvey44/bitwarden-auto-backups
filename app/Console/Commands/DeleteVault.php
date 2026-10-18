<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsVaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('vaults:delete
    {vault : The ID of the vault to delete}
    {--keep-files : Keep the backup files on disk, only deleting the vault and its backup history}
    {--force : Delete without asking for confirmation}')]
#[Description('Delete a vault, its backup history and its backup files')]
class DeleteVault extends Command
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

        $fileCount = $vault->backupFiles()->count();

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Pass --force to delete a vault without confirmation.');

                return self::FAILURE;
            }

            $consequence = $this->option('keep-files')
                ? "Its {$fileCount} backup file(s) will be kept on disk, but the passwords stored for them will be deleted."
                : "Its {$fileCount} backup file(s) will be deleted from disk.";

            if (! confirm(label: "Delete vault [{$vault->name}]?", default: false, hint: $consequence)) {
                $this->components->warn('Nothing was deleted.');

                return self::FAILURE;
            }
        }

        $vault->deleteWithBackups(keepFiles: $this->option('keep-files'));

        $this->components->info("Vault [{$vault->name}] deleted.");

        return self::SUCCESS;
    }
}
