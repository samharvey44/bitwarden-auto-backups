<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfiguresVaults;
use App\Enums\BackupFormat;
use App\Enums\BackupFrequency;
use App\Models\User;
use App\Services\VaultSettings;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('vaults:create
    {--user= : The email address of the user who owns the vault}
    {--name= : A name to identify the vault by}
    {--server-url= : The server URL, such as "https://vault.bitwarden.com" for the US cloud or "https://vault.bitwarden.eu" for the EU cloud}
    {--format=* : A format to back up in, "encrypted_json" or "json". Repeat for both [default: "encrypted_json"]}
    {--frequency= : How often to back up, "daily", "weekly" or "monthly" [default: "daily"]}
    {--retain= : How many successful backups to keep [default: 30]}')]
#[Description('Add a Bitwarden vault to back up')]
#[Help(<<<'HELP'
Secrets are never accepted as options, as they would be left in your shell history. When prompting, they are asked for with hidden input. Otherwise, set them in these environment variables:

  VAULT_CLIENT_ID                       The API key client_id
  VAULT_CLIENT_SECRET                   The API key client_secret
  VAULT_MASTER_PASSWORD                 The master password
  VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD  The password encrypted JSON backups are protected with
  VAULT_JSON_EXPORT_PASSWORD            The password JSON backups are zipped with. Leave unset for plain JSON
HELP)]
class CreateVault extends Command
{
    use ConfiguresVaults;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('user') === null && $this->input->isInteractive() && User::query()->doesntExist()) {
            $this->components->error('No users exist yet. Create one with users:create first.');

            return self::FAILURE;
        }

        $secrets = $this->secretsFromEnvironment();

        $input = [
            'user' => $this->option('user') ?? $this->whenInteractive($this->promptForUser(...)),
            'name' => $this->option('name') ?? $this->whenInteractive(fn (): string => text(
                label: 'What should the vault be called?',
                placeholder: 'Personal',
                required: true,
                validate: ['name' => VaultSettings::rules()['name']],
            )),
            'server_url' => $this->option('server-url') ?? $this->whenInteractive($this->promptForServerUrl(...)),
            ...($this->whenInteractive(fn (): array => $this->promptForCredentials($secrets)) ?? []),
            ...$secrets,
        ];

        $input['formats'] = $this->option('format') ?: ($this->whenInteractive($this->promptForFormats(...)) ?? [BackupFormat::EncryptedJson->value]);

        foreach ([BackupFormat::EncryptedJson, BackupFormat::Json] as $format) {
            $setting = "{$format->value}_export_password";

            if (in_array($format->value, $input['formats'], true) && ! isset($secrets[$setting])) {
                $input[$setting] = $this->whenInteractive(fn (): ?string => $this->promptForExportPassword($format));
            }
        }

        $input['frequency'] = $this->option('frequency') ?? $this->whenInteractive($this->promptForFrequency(...)) ?? BackupFrequency::Daily->value;
        $input['retain_backups_count'] = $this->option('retain') ?? $this->whenInteractive($this->promptForRetainBackupsCount(...)) ?? 30;

        $validated = $this->validateInput($input, [
            'user' => [
                'bail',
                'required',
                'email',
                Rule::exists(User::class, 'email'),
                function (string $attribute, string $email, Closure $fail): void {
                    if (! User::query()->where('email', $email)->sole()->canAddVault()) {
                        $fail('User ['.$email.'] already has the maximum of '.config('backups.max_vaults_per_user').' vaults. Raise MAX_VAULTS_PER_USER, or set it to 0, to add more.');
                    }
                },
            ],
            ...VaultSettings::rules($input),
        ], [
            'user.required' => 'The owner is required. Pass their email address with --user.',
            'user.exists' => 'No user has the email address ['.$input['user'].']. Create them with users:create first.',
            'name.required' => 'The name is required. Pass it with --name.',
            ...$this->vaultMessages(),
        ]);

        if ($validated === null) {
            return self::FAILURE;
        }

        $vault = User::query()->where('email', $validated['user'])->sole()->vaults()->create(VaultSettings::attributes($validated));

        $this->components->info("Vault [{$vault->name}] created with ID [{$vault->id}]. Its first backup will be queued the next time the scheduler runs.");

        if ($this->input->isInteractive() && confirm('Check that the credentials work now?')) {
            $this->call('vaults:verify', ['vault' => $vault->id]);
        } else {
            $this->components->bulletList(["Run [vaults:verify {$vault->id}] to check that its credentials work."]);
        }

        return self::SUCCESS;
    }

    /**
     * Ask which user owns the vault, returning their email address.
     */
    private function promptForUser(): string
    {
        return select(
            label: 'Which user owns the vault?',
            options: User::query()->orderBy('email')->pluck('email', 'email')->all(),
        );
    }
}
