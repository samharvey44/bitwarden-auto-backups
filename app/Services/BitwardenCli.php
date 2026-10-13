<?php

namespace App\Services;

use App\Enums\BackupFormat;
use App\Exceptions\BitwardenCliException;
use App\Models\Vault;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class BitwardenCli
{
    /**
     * Export the vault once per requested format, logging in only once.
     *
     * @param  array<value-of<BackupFormat>, string>  $destinations  The file to write each export to, keyed by format.
     *
     * @throws BitwardenCliException
     */
    public function exportVault(Vault $vault, array $destinations): void
    {
        $dataDirectory = $this->makeDataDirectory();
        $environment = ['BITWARDENCLI_APPDATA_DIR' => $dataDirectory];

        try {
            if ($vault->server_url !== null) {
                $this->run(['config', 'server', $vault->server_url], $environment);
            }

            $this->run(['login', '--apikey'], [
                ...$environment,
                'BW_CLIENTID' => $vault->client_id,
                'BW_CLIENTSECRET' => $vault->client_secret,
            ]);

            $sessionKey = trim($this->run(['unlock', '--passwordenv', 'BW_PASSWORD', '--raw'], [
                ...$environment,
                'BW_PASSWORD' => $vault->master_password,
            ])->output());

            $sessionEnvironment = [...$environment, 'BW_SESSION' => $sessionKey];

            $this->run(['sync'], $sessionEnvironment);

            foreach ($destinations as $format => $destination) {
                $this->run($this->exportArguments($vault, BackupFormat::from($format), $destination), $sessionEnvironment);
            }
        } finally {
            $this->logOut($environment);

            File::deleteDirectory($dataDirectory);
        }
    }

    /**
     * Build the CLI arguments that export the vault in the given format.
     *
     * @return list<string>
     */
    private function exportArguments(Vault $vault, BackupFormat $format, string $destination): array
    {
        return match ($format) {
            BackupFormat::Json => ['export', '--format', 'json', '--output', $destination],

            // The CLI only accepts the export password as an argument (or an
            // interactive prompt), so unlike the credentials, it is briefly
            // visible in the container's process list while the export runs.
            BackupFormat::EncryptedJson => [
                'export',
                '--format', 'encrypted_json',
                '--password', $vault->encrypted_json_export_password,
                '--output', $destination,
            ],
        };
    }

    /**
     * Run a CLI command and return its result.
     *
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     *
     * @throws BitwardenCliException
     */
    private function run(array $arguments, array $environment): ProcessResult
    {
        $result = Process::timeout(config('backups.cli.timeout'))
            ->env($environment)
            ->run([config('backups.cli.path'), '--nointeraction', ...$arguments]);

        if ($result->failed()) {
            throw BitwardenCliException::commandFailed($arguments[0], $result);
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

    /**
     * Create a private, empty data directory for a single CLI session.
     */
    private function makeDataDirectory(): string
    {
        $path = config('backups.working_directory').'/'.Str::uuid();

        File::makeDirectory($path, 0700, true);

        return $path;
    }
}
