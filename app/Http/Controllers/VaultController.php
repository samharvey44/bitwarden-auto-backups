<?php

namespace App\Http\Controllers;

use App\Enums\BackupFrequency;
use App\Http\Requests\DeleteVaultRequest;
use App\Http\Requests\EditVaultRequest;
use App\Http\Requests\ShowVaultRequest;
use App\Http\Requests\StoreVaultRequest;
use App\Http\Requests\UpdateVaultRequest;
use App\Http\Resources\BackupResource;
use App\Http\Resources\VaultResource;
use App\Models\Backup;
use App\Models\Vault;
use App\Services\VaultSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VaultController extends Controller
{
    /**
     * List the user's vaults.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('vaults/Index', [
            'vaults' => VaultResource::collection($request->user()->vaults()->with('latestBackup')->orderBy('name')->orderBy('id')->get())->resolve(),
        ]);
    }

    /**
     * Show the form for adding a vault.
     */
    public function create(): Response
    {
        return Inertia::render('vaults/Create', $this->formOptions());
    }

    /**
     * Add a vault for the user.
     */
    public function store(StoreVaultRequest $request): RedirectResponse
    {
        $vault = $request->user()->vaults()->create(VaultSettings::attributes($request->validated()));

        Inertia::flash('success', "{$vault->name} was added. Check its credentials work before its first backup runs.");

        return to_route('vaults.show', $vault);
    }

    /**
     * Show a vault's settings and its backups, newest first.
     */
    public function show(ShowVaultRequest $request, Vault $vault): Response
    {
        return Inertia::render('vaults/Show', [
            'vault' => VaultResource::make($vault)->resolve(),
            'backups' => $vault->backups()
                ->with('files')
                ->latest()
                ->latest('id')
                ->paginate(10)
                ->through(fn (Backup $backup): array => BackupResource::make($backup)->resolve()),
            'backupInProgress' => $vault->hasBackupInProgress(),
            'backupFileCount' => $vault->backupFiles()->count(),
        ]);
    }

    /**
     * Show the form for changing a vault's settings.
     */
    public function edit(EditVaultRequest $request, Vault $vault): Response
    {
        return Inertia::render('vaults/Edit', [
            ...$this->formOptions(),
            'vault' => VaultResource::make($vault)->resolve(),
        ]);
    }

    /**
     * Save a vault's changed settings.
     */
    public function update(UpdateVaultRequest $request, Vault $vault): RedirectResponse
    {
        VaultSettings::fill($vault, $request->validated());

        $credentialsChanged = $vault->isDirty(['server_url', 'client_id', 'client_secret', 'master_password']);

        $vault->save();

        Inertia::flash('success', $credentialsChanged
            ? "{$vault->name} was saved. Check its new credentials work."
            : "{$vault->name} was saved.");

        return to_route('vaults.show', $vault);
    }

    /**
     * Delete a vault and its backup history, and its backup files unless
     * they should be kept.
     */
    public function destroy(DeleteVaultRequest $request, Vault $vault): RedirectResponse
    {
        $vault->deleteWithBackups(keepFiles: $request->boolean('keep_files'));

        Inertia::flash('success', "{$vault->name} was deleted.");

        return to_route('vaults.index');
    }

    /**
     * Get the choices offered by the vault form.
     *
     * @return array{frequencies: list<array{value: string, label: string}>, cloudServerUrls: array{us: string, eu: string}}
     */
    private function formOptions(): array
    {
        return [
            'frequencies' => array_map(
                fn (BackupFrequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
                BackupFrequency::cases(),
            ),
            'cloudServerUrls' => [
                'us' => VaultSettings::US_SERVER_URL,
                'eu' => VaultSettings::EU_SERVER_URL,
            ],
        ];
    }
}
