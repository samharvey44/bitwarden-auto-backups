<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsVaults;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vaults:verify {vault : The ID of the vault to check}')]
#[Description("Check that a vault's server, API key and master password work, without backing it up")]
class VerifyVault extends Command
{
    use FindsVaults;

    /**
     * Execute the console command.
     */
    public function handle(BitwardenCli $bitwarden): int
    {
        $vault = $this->findVault();

        if ($vault === null) {
            return self::FAILURE;
        }

        try {
            $this->components->task(
                "Logging in to and unlocking vault [{$vault->name}]",
                fn () => $bitwarden->verifyCredentials($vault),
            );
        } catch (BitwardenCliException $exception) {
            $this->components->error($exception->getMessage());
            $this->components->bulletList(["Update the vault's settings with [vaults:edit {$vault->id}]."]);

            return self::FAILURE;
        }

        $this->components->info("The credentials for vault [{$vault->name}] work.");

        return self::SUCCESS;
    }
}
