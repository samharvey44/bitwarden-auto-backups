<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesInput;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('users:create
    {--name= : The user\'s name}
    {--email= : The user\'s email address}')]
#[Description('Create a user who can own vaults')]
#[Help(<<<'HELP'
The password is never accepted as an option, as it would be left in your shell history. When prompting, it is asked for with hidden input. Otherwise, set it in the USER_PASSWORD environment variable.
HELP)]
class CreateUser extends Command
{
    use ValidatesInput;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults()],
        ];

        $input = [
            'name' => $this->option('name') ?? $this->whenInteractive(fn (): string => text(
                label: 'Name',
                required: true,
                validate: ['name' => $rules['name']],
            )),
            'email' => $this->option('email') ?? $this->whenInteractive(fn (): string => text(
                label: 'Email address',
                required: true,
                validate: ['email' => $rules['email']],
            )),
            'password' => $this->secretFromEnvironment('USER_PASSWORD') ?? $this->whenInteractive(fn (): string => $this->promptForPassword($rules['password'])),
        ];

        $validated = $this->validateInput($input, $rules, [
            'name.required' => 'The name is required. Pass it with --name.',
            'email.required' => 'The email address is required. Pass it with --email.',
            'password.required' => 'The password is required. Set it in the USER_PASSWORD environment variable.',
        ]);

        if ($validated === null) {
            return self::FAILURE;
        }

        $user = User::create($validated);

        $this->components->info("User [{$user->email}] created. Add a vault for them with vaults:create.");

        return self::SUCCESS;
    }

    /**
     * Ask for the password twice, so a typo cannot lock the user out.
     *
     * @param  array<int, mixed>  $rules
     */
    private function promptForPassword(array $rules): string
    {
        $password = password(label: 'Password', required: true, validate: ['password' => $rules]);

        password(
            label: 'Enter the password again to confirm',
            required: true,
            validate: fn (string $confirmation): ?string => $confirmation === $password ? null : 'The passwords do not match.',
        );

        return $password;
    }
}
