<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyVaultRequest;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VerifyVaultController extends Controller
{
    /**
     * Check that the vault's server, API key and master password work by
     * logging in and unlocking it, without backing it up.
     *
     * This runs the Bitwarden CLI during the request, so it takes a few
     * seconds.
     */
    public function __invoke(VerifyVaultRequest $request, Vault $vault, BitwardenCli $bitwarden): RedirectResponse
    {
        try {
            $bitwarden->verifyCredentials($vault);
        } catch (BitwardenCliException $exception) {
            Inertia::flash('error', $exception->getMessage());

            return back();
        }

        Inertia::flash('success', "The credentials for {$vault->name} work.");

        return back();
    }
}
