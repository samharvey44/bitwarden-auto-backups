<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsVaults;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backups:run {vault : The ID of the vault to back up}')]
#[Description('Queue an immediate backup of a vault, outside its schedule')]
class RunVaultBackup extends Command
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

        if ($vault->enabledFormats() === []) {
            $this->components->error("Vault [{$vault->name}] has no backup formats enabled.");

            return self::FAILURE;
        }

        if ($vault->hasBackupInProgress()) {
            $this->components->error("A backup of vault [{$vault->name}] is already queued or running.");

            return self::FAILURE;
        }

        $backup = $vault->queueBackup();

        $this->components->info("Queued backup [{$backup->id}] of vault [{$vault->name}].");

        return self::SUCCESS;
    }
}
