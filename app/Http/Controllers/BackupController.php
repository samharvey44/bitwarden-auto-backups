<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupRequest;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BackupController extends Controller
{
    /**
     * Queue a backup of the vault now, outside its schedule.
     */
    public function store(StoreBackupRequest $request, Vault $vault): RedirectResponse
    {
        $vault->queueBackup();

        Inertia::flash('success', "A backup of {$vault->name} was queued. This page updates as it runs.");

        return to_route('vaults.show', $vault);
    }
}
