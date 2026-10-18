<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Auth\Access\Response;

/**
 * Users can only see and manage their own vaults. Other users' vaults are
 * reported as not found, so their existence is never revealed.
 */
class VaultPolicy
{
    /**
     * Determine whether the user can view the vault and its backups.
     */
    public function view(User $user, Vault $vault): Response
    {
        return $this->owns($user, $vault);
    }

    /**
     * Determine whether the user can change the vault's settings, check its
     * credentials or back it up.
     */
    public function update(User $user, Vault $vault): Response
    {
        return $this->owns($user, $vault);
    }

    /**
     * Determine whether the user can delete the vault.
     */
    public function delete(User $user, Vault $vault): Response
    {
        return $this->owns($user, $vault);
    }

    /**
     * Allow the vault's owner, and hide it from everyone else.
     */
    private function owns(User $user, Vault $vault): Response
    {
        return $vault->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
