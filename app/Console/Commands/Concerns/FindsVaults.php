<?php

namespace App\Console\Commands\Concerns;

use App\Models\Vault;

/**
 * Look up the vault named by a command's "vault" argument.
 */
trait FindsVaults
{
    /**
     * Find the vault with the ID given as the "vault" argument, printing an
     * error when it does not exist.
     */
    protected function findVault(): ?Vault
    {
        $vault = Vault::find($this->argument('vault'));

        if ($vault === null) {
            $this->components->error("Vault [{$this->argument('vault')}] does not exist.");
        }

        return $vault;
    }
}
