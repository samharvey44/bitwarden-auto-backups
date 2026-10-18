<?php

namespace App\Console\Commands\Concerns;

use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Helpers for commands that collect input from options, prompts and
 * environment variables, then validate it all together.
 */
trait ValidatesInput
{
    /**
     * Run the prompt when the command is interactive, otherwise return null so
     * validation can report the missing value.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $prompt
     * @return TValue|null
     */
    protected function whenInteractive(Closure $prompt): mixed
    {
        return $this->input->isInteractive() ? $prompt() : null;
    }

    /**
     * Read a secret from an environment variable of the running process.
     *
     * Secrets are never accepted as options, which would leave them in shell
     * history and the process list. They are deliberately read straight from
     * the process environment rather than through config, so they are never
     * written to the config cache.
     */
    protected function secretFromEnvironment(string $variable): ?string
    {
        $value = getenv($variable);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Validate the input, printing every error when it fails.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>|null The validated input, or null when validation failed.
     */
    protected function validateInput(array $input, array $rules, array $messages = []): ?array
    {
        $validator = Validator::make($input, $rules, $messages, $this->validationAttributes());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return null;
        }

        return $validator->validated();
    }
}
