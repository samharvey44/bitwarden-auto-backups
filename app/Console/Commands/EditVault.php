<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfiguresVaults;
use App\Console\Commands\Concerns\FindsVaults;
use App\Enums\BackupFormat;
use App\Models\Vault;
use App\Services\VaultSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

#[Signature('vaults:edit
    {vault : The ID of the vault to edit}
    {--name= : A name to identify the vault by}
    {--server-url= : The server URL, such as "https://vault.bitwarden.com" for the US cloud or "https://vault.bitwarden.eu" for the EU cloud}
    {--format=* : A format to back up in, "encrypted_json" or "json". Repeat for both. Replaces the current formats}
    {--frequency= : How often to back up, "hourly", "daily", "weekly" or "monthly"}
    {--retain= : How many successful backups to keep}
    {--without-json-export-password : Save JSON backups as plain, unencrypted JSON instead of a password-protected ZIP}')]
#[Description("Change a vault's settings")]
#[Help(<<<'HELP'
Without any options or environment variables, asks which settings to change.

Secrets are never accepted as options, as they would be left in your shell history. When prompting, they are asked for with hidden input. To change them without prompting, set them in these environment variables:

  VAULT_CLIENT_ID                       The API key client_id
  VAULT_CLIENT_SECRET                   The API key client_secret
  VAULT_MASTER_PASSWORD                 The master password
  VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD  The password encrypted JSON backups are protected with
  VAULT_JSON_EXPORT_PASSWORD            The password JSON backups are zipped with. Leave unset for plain JSON
HELP)]
class EditVault extends Command
{
    use ConfiguresVaults;
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

        $changes = $this->changesFromOptions();

        if ($changes === []) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Nothing to change. Pass an option, or set a VAULT_* environment variable.');

                return self::FAILURE;
            }

            $changes = $this->promptForChanges($vault);
        }

        $input = [...VaultSettings::fromVault($vault), ...$changes];

        $validated = $this->validateInput($input, VaultSettings::rules($input), $this->vaultMessages());

        if ($validated === null) {
            return self::FAILURE;
        }

        VaultSettings::fill($vault, $validated);

        $credentialsChanged = $vault->isDirty(['server_url', 'client_id', 'client_secret', 'master_password']);

        $vault->save();

        $this->components->info("Vault [{$vault->name}] updated.");

        if ($credentialsChanged && $this->input->isInteractive() && confirm('Check that the new credentials work now?')) {
            $this->call('vaults:verify', ['vault' => $vault->id]);
        } elseif ($credentialsChanged) {
            $this->components->bulletList(["Run [vaults:verify {$vault->id}] to check that its credentials work."]);
        }

        return self::SUCCESS;
    }

    /**
     * Get the settings changed by options and environment variables.
     *
     * @return array<string, mixed>
     */
    private function changesFromOptions(): array
    {
        $changes = array_filter([
            'name' => $this->option('name'),
            'server_url' => $this->option('server-url'),
            'formats' => $this->option('format') ?: null,
            'frequency' => $this->option('frequency'),
            'retain_backups_count' => $this->option('retain'),
        ], fn (mixed $value): bool => $value !== null);

        if ($this->option('without-json-export-password')) {
            $changes['json_export_password'] = null;
        }

        return [...$changes, ...$this->secretsFromEnvironment()];
    }

    /**
     * Ask which settings to change, then prompt for their new values.
     *
     * @return array<string, mixed>
     */
    private function promptForChanges(Vault $vault): array
    {
        $sections = multiselect(
            label: 'What would you like to change?',
            options: [
                'name' => "Name ({$vault->name})",
                'server' => 'Server',
                'credentials' => 'API key and master password',
                'exports' => 'Formats and export passwords',
                'schedule' => "Schedule ({$vault->frequency->label()}, keeping {$vault->retain_backups_count} backups)",
            ],
            required: 'Choose at least one setting to change.',
        );

        $changes = [];

        if (in_array('name', $sections, true)) {
            $changes['name'] = text(
                label: 'What should the vault be called?',
                default: $vault->name,
                required: true,
                validate: ['name' => VaultSettings::rules()['name']],
            );
        }

        if (in_array('server', $sections, true)) {
            $changes['server_url'] = $this->promptForServerUrl($vault->server_url);
        }

        if (in_array('credentials', $sections, true)) {
            $changes = [...$changes, ...$this->promptForCredentials()];
        }

        if (in_array('exports', $sections, true)) {
            $changes = [...$changes, ...$this->promptForExports($vault)];
        }

        if (in_array('schedule', $sections, true)) {
            $changes['frequency'] = $this->promptForFrequency($vault->frequency);
            $changes['retain_backups_count'] = $this->promptForRetainBackupsCount($vault->retain_backups_count);
        }

        return $changes;
    }

    /**
     * Ask which formats to back up in, and for the password of each newly
     * enabled format, or of an existing one when it is being changed.
     *
     * @return array<string, mixed>
     */
    private function promptForExports(Vault $vault): array
    {
        $enabledFormats = $vault->enabledFormats();
        $changes = ['formats' => $this->promptForFormats(array_map(fn (BackupFormat $format): string => $format->value, $enabledFormats))];

        foreach ([BackupFormat::EncryptedJson, BackupFormat::Json] as $format) {
            if (! in_array($format->value, $changes['formats'], true)) {
                continue;
            }

            if (! in_array($format, $enabledFormats, true) || $this->confirmExportPasswordChange($format)) {
                $changes["{$format->value}_export_password"] = $this->promptForExportPassword($format);
            }
        }

        return $changes;
    }
}
