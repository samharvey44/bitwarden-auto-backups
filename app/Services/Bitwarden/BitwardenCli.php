<?php

namespace App\Services\Bitwarden;

use App\Enums\BackupFormat;
use App\Models\Vault;
use App\Services\Bitwarden\DTOs\VaultExport;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BitwardenCli
{
    /**
     * Export the vault in each requested format, logging in only once.
     *
     * Exports are read from the CLI's standard output rather than written to
     * disk, so an unencrypted export never touches the filesystem.
     *
     * @return list<VaultExport>
     *
     * @throws BitwardenCliException
     */
    public function exportVault(Vault $vault, BackupFormat ...$formats): array
    {
        return $this->whileUnlocked($vault, function (array $sessionEnvironment) use ($vault, $formats): array {
            $this->run(['sync'], $sessionEnvironment);

            $exports = [];

            foreach ($formats as $format) {
                $contents = $this->run($this->exportArguments($vault, $format), $sessionEnvironment, outputIsSensitive: true)->output();

                throw_if($contents === '', BitwardenCliException::emptyExport($format));

                $exports[] = new VaultExport($format, $contents);
            }

            return $exports;
        });
    }

    /**
     * Check that the vault's server, API key and master password work by
     * logging in and unlocking, without exporting anything.
     *
     * @throws BitwardenCliException
     */
    public function verifyCredentials(Vault $vault): void
    {
        $this->whileUnlocked($vault, fn (): null => null);
    }

    /**
     * Log in to and unlock the vault in a fresh CLI data directory, seeded with
     * the vault's device ID, run the callback with the unlocked session's
     * environment, then log out and remove the data directory.
     *
     * @template TResult
     *
     * @param  Closure(array<string, string>): TResult  $callback
     * @return TResult
     *
     * @throws BitwardenCliException
     */
    private function whileUnlocked(Vault $vault, Closure $callback): mixed
    {
        $workingDisk = Storage::disk(config('backups.working_disk'));
        $dataDirectory = (string) Str::uuid();

        $workingDisk->makeDirectory($dataDirectory);

        // The CLI reports this ID to Bitwarden as the device logging in. Giving it
        // the vault's own ID makes every backup the same device, rather than a new
        // one each run, which would send a "new device" email every time.
        $workingDisk->put("{$dataDirectory}/data.json", json_encode(['global_applicationId_appId' => $vault->bitwarden_device_id]));

        $environment = ['BITWARDENCLI_APPDATA_DIR' => $workingDisk->path($dataDirectory)];

        try {
            $this->run(['config', 'server', $vault->server_url], $environment);

            $this->run(['login', '--apikey'], [
                ...$environment,
                'BW_CLIENTID' => $vault->client_id,
                'BW_CLIENTSECRET' => $vault->client_secret,
            ]);

            $sessionKey = trim($this->run(['unlock', '--passwordenv', 'BW_PASSWORD', '--raw'], [
                ...$environment,
                'BW_PASSWORD' => $vault->master_password,
            ])->output());

            return $callback([...$environment, 'BW_SESSION' => $sessionKey]);
        } finally {
            $this->logOut($environment);

            $workingDisk->deleteDirectory($dataDirectory);
        }
    }

    /**
     * Build the CLI arguments that export the vault in the given format to
     * standard output.
     *
     * @return list<string>
     */
    private function exportArguments(Vault $vault, BackupFormat $format): array
    {
        return match ($format) {
            BackupFormat::Json => ['export', '--format', 'json', '--raw'],

            // The CLI only accepts the export password as an argument (or an
            // interactive prompt), so unlike the credentials, it is briefly
            // visible in the container's process list while the export runs.
            BackupFormat::EncryptedJson => [
                'export',
                '--format', 'encrypted_json',
                '--password', $vault->encrypted_json_export_password,
                '--raw',
            ],
        };
    }

    /**
     * Run a CLI command and return its result.
     *
     * When the command's output is sensitive, such as a vault export, it is
     * left out of the exception thrown if the command fails.
     *
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     *
     * @throws BitwardenCliException
     */
    private function run(array $arguments, array $environment, bool $outputIsSensitive = false): ProcessResult
    {
        try {
            $result = Process::timeout(config('backups.cli.timeout'))
                ->env($environment)
                ->run([config('backups.cli.path'), '--nointeraction', ...$arguments]);
        } catch (ProcessTimedOutException) {
            // The timeout exception's message repeats the full command line,
            // which includes the export password for encrypted JSON exports.
            // It is replaced, and deliberately not chained as the previous
            // exception, so the password never reaches logs, the failed jobs
            // table or the backup's recorded error.
            throw BitwardenCliException::commandTimedOut($arguments[0]);
        }

        if ($result->failed()) {
            throw BitwardenCliException::commandFailed($arguments[0], $result, $outputIsSensitive);
        }

        return $result;
    }

    /**
     * Log out of the account.
     */
    private function logOut(array $environment): void
    {
        rescue(fn () => Process::timeout(config('backups.cli.timeout'))
            ->env($environment)
            ->run([config('backups.cli.path'), '--nointeraction', 'logout']), report: false);
    }
}
