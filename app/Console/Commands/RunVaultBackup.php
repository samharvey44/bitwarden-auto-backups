<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Jobs\BackupVault;
use App\Models\Vault;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backups:run {vault : The ID of the vault to back up}')]
#[Description('Queue an immediate backup of a vault, outside its schedule')]
class RunVaultBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vault = Vault::find($this->argument('vault'));

        if ($vault === null) {
            $this->components->error("Vault [{$this->argument('vault')}] does not exist.");

            return self::FAILURE;
        }

        if ($vault->enabledFormats() === []) {
            $this->components->error("Vault [{$vault->name}] has no backup formats enabled.");

            return self::FAILURE;
        }

        $backup = $vault->backups()->create(['status' => BackupStatus::Pending]);

        BackupVault::dispatch($backup);

        $this->components->info("Queued backup [{$backup->id}] of vault [{$vault->name}].");

        return self::SUCCESS;
    }
}
