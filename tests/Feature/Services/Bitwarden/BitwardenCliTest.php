<?php

namespace Tests\Feature\Services\Bitwarden;

use App\Enums\BackupFormat;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\DTOs\VaultExport;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class BitwardenCliTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backups.cli.path' => '/opt/bw']);
    }

    public function test_configures_the_server_then_logs_in_unlocks_syncs_exports_and_logs_out_in_order(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        $this->assertSame([
            ['/opt/bw', '--nointeraction', 'config', 'server', 'https://vault.bitwarden.com'],
            ['/opt/bw', '--nointeraction', 'login', '--apikey'],
            ['/opt/bw', '--nointeraction', 'unlock', '--passwordenv', 'BW_PASSWORD', '--raw'],
            ['/opt/bw', '--nointeraction', 'sync'],
            ['/opt/bw', '--nointeraction', 'export', '--format', 'encrypted_json', '--password='.$vault->encrypted_json_export_password, '--raw'],
            ['/opt/bw', '--nointeraction', 'logout'],
        ], $processes->pluck('command')->all());
    }

    public function test_exports_every_requested_format_from_a_single_login(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withJsonExport()->create();
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault(
            $vault,
            BackupFormat::Json,
            BackupFormat::EncryptedJson,
        );

        $this->assertSame([
            ['/opt/bw', '--nointeraction', 'config', 'server', 'https://vault.bitwarden.com'],
            ['/opt/bw', '--nointeraction', 'login', '--apikey'],
            ['/opt/bw', '--nointeraction', 'unlock', '--passwordenv', 'BW_PASSWORD', '--raw'],
            ['/opt/bw', '--nointeraction', 'sync'],
            ['/opt/bw', '--nointeraction', 'export', '--format', 'json', '--raw'],
            ['/opt/bw', '--nointeraction', 'export', '--format', 'encrypted_json', '--password='.$vault->encrypted_json_export_password, '--raw'],
            ['/opt/bw', '--nointeraction', 'logout'],
        ], $processes->pluck('command')->all());
    }

    public function test_returns_each_export_read_from_standard_output(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withJsonExport()->create();
        $this->fakeCli();

        $exports = app(BitwardenCli::class)->exportVault($vault, BackupFormat::Json, BackupFormat::EncryptedJson);

        // Faked process output always ends in a newline.
        $this->assertEquals([
            new VaultExport(BackupFormat::Json, "{\"format\":\"json\"}\n"),
            new VaultExport(BackupFormat::EncryptedJson, "{\"format\":\"encrypted_json\"}\n"),
        ], $exports);
        $this->assertSame([], Storage::disk('bitwarden-cli')->allFiles());
    }

    public function test_empty_export_throws_and_still_logs_out(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli(failing: [
            'export' => Process::result(),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertSame('Bitwarden CLI command [export] returned an empty encrypted_json export.', $exception->getMessage());
        }

        $this->assertSame('logout', $processes->last()->command[2]);
    }

    public function test_verifying_credentials_logs_in_and_unlocks_without_syncing_or_exporting(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->verifyCredentials($vault);

        $this->assertSame(['config', 'login', 'unlock', 'logout'], $processes->pluck('command.2')->all());
        $this->assertSame([], Storage::disk('bitwarden-cli')->directories());
    }

    public function test_logs_in_as_the_vaults_own_device_on_every_run(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $seededDataFiles = [];
        $this->fakeCli(failing: [
            'login' => function (PendingProcess $process) use (&$seededDataFiles) {
                $seededDataFiles[] = json_decode(file_get_contents($process->environment['BITWARDENCLI_APPDATA_DIR'].'/data.json'), true);

                return Process::result();
            },
        ]);

        app(BitwardenCli::class)->verifyCredentials($vault);
        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        $this->assertSame([
            ['global_applicationId_appId' => $vault->bitwarden_device_id],
            ['global_applicationId_appId' => $vault->bitwarden_device_id],
        ], $seededDataFiles);
    }

    public function test_passes_an_export_password_starting_with_a_dash_as_its_value(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create(['encrypted_json_export_password' => '-Correct-Horse-9']);
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        $this->assertContains(
            ['/opt/bw', '--nointeraction', 'export', '--format', 'encrypted_json', '--password=-Correct-Horse-9', '--raw'],
            $processes->pluck('command')->all(),
        );
    }

    public function test_never_passes_this_apps_secrets_to_the_cli(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $this->withProcessEnvironment([
            'DB_PASSWORD' => 'database-secret',
            'MAIL_PASSWORD' => 'mail-secret',
            'AWS_SECRET_ACCESS_KEY' => 'aws-secret',
        ]);
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        foreach ($processes as $process) {
            foreach (['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY'] as $secret) {
                $this->assertFalse($process->environment[$secret], "{$secret} was passed to [{$process->command[2]}].");
            }

            $this->assertArrayNotHasKey('PATH', $process->environment, 'PATH should still be inherited, so the CLI can find Node.');
        }
    }

    public function test_configures_a_non_us_server_url_before_logging_in(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withServerUrl('https://vault.bitwarden.eu')->create();
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        $this->assertSame(
            [['/opt/bw', '--nointeraction', 'config', 'server', 'https://vault.bitwarden.eu'], ['/opt/bw', '--nointeraction', 'login', '--apikey']],
            $processes->pluck('command')->take(2)->all(),
        );
    }

    public function test_passes_credentials_and_session_key_through_the_environment(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $this->fakeCli(sessionKey: 'session-key-123');

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        Process::assertRan(fn (PendingProcess $process): bool => $process->command[2] === 'login'
            && $process->environment['BW_CLIENTID'] === $vault->client_id
            && $process->environment['BW_CLIENTSECRET'] === $vault->client_secret);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command[2] === 'unlock'
            && $process->environment['BW_PASSWORD'] === $vault->master_password);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command[2] === 'export'
            && $process->environment['BW_SESSION'] === 'session-key-123');
    }

    public function test_never_passes_api_secret_or_master_password_as_arguments(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        Process::assertDidntRun(fn (PendingProcess $process): bool => in_array($vault->client_secret, $process->command, true)
            || in_array($vault->master_password, $process->command, true));
    }

    public function test_every_command_shares_one_data_directory_that_is_removed_afterwards(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli();

        app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);

        $dataDirectories = $processes->pluck('environment.BITWARDENCLI_APPDATA_DIR')->unique();
        $this->assertCount(1, $dataDirectories);
        $this->assertStringStartsWith(Storage::disk('bitwarden-cli')->path(''), $dataDirectories->first());
        $this->assertDirectoryDoesNotExist($dataDirectories->first());
    }

    public function test_failed_login_throws_without_exporting_and_still_logs_out(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli(failing: [
            'login' => Process::result(errorOutput: 'client_id or client_secret is incorrect. Try again.', exitCode: 1),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertSame(
                'Bitwarden CLI command [login] failed with exit code 1: client_id or client_secret is incorrect. Try again.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(['config', 'login', 'logout'], $processes->pluck('command.2')->all());
        $this->assertSame([], Storage::disk('bitwarden-cli')->directories());
    }

    public function test_failure_message_hides_the_cli_data_file_notices(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $this->fakeCli(failing: [
            'login' => Process::result(
                errorOutput: "Could not find dir, \"/tmp/cli\"; creating it instead.\nCould not find data file, \"/tmp/cli/data.json\"; creating it instead.\nclient_id or client_secret is incorrect. Try again.\n",
                exitCode: 1,
            ),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertSame(
                'Bitwarden CLI command [login] failed with exit code 1: client_id or client_secret is incorrect. Try again.',
                $exception->getMessage(),
            );
        }
    }

    public function test_failed_export_error_does_not_reveal_the_export_password(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->create();
        $this->fakeCli(failing: [
            'export' => Process::result(errorOutput: 'Export failed.', exitCode: 1),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertStringNotContainsString($vault->encrypted_json_export_password, $exception->getMessage());
        }
    }

    public function test_timed_out_export_error_does_not_reveal_the_export_password(): void
    {
        Storage::fake('bitwarden-cli');
        config(['backups.cli.timeout' => 60]);
        $vault = Vault::factory()->create();
        $processes = $this->fakeCli(failing: [
            'export' => fn (PendingProcess $process) => throw ProcessTimedOutException::make(
                new SymfonyTimeoutException(new SymfonyProcess($process->command), SymfonyTimeoutException::TYPE_GENERAL),
                Process::result(),
            ),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::EncryptedJson);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertSame('Bitwarden CLI command [export] timed out after 60 seconds.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString($vault->encrypted_json_export_password, (string) $exception);
        }

        $this->assertSame('logout', $processes->last()->command[2]);
    }

    public function test_failed_export_error_never_falls_back_to_the_exported_data(): void
    {
        Storage::fake('bitwarden-cli');
        $vault = Vault::factory()->withJsonExport()->create();
        $this->fakeCli(failing: [
            'export' => Process::result(output: '{"items":[{"login":{"password":"hunter2"}}]}', exitCode: 1),
        ]);

        try {
            app(BitwardenCli::class)->exportVault($vault, BackupFormat::Json);
            $this->fail('Expected the export to throw a BitwardenCliException.');
        } catch (BitwardenCliException $exception) {
            $this->assertSame('Bitwarden CLI command [export] failed with exit code 1: no output', $exception->getMessage());
        }
    }

    /**
     * Fake every CLI command, recording each process in the order it runs.
     * Exports write a small JSON document naming their format to standard output.
     *
     * @param  array<string, mixed>  $failing  Fake results, or closures that build them, keyed by CLI subcommand.
     * @return Collection<int, PendingProcess>
     */
    private function fakeCli(string $sessionKey = 'session-key', array $failing = []): Collection
    {
        $processes = collect();

        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) use ($processes, $sessionKey, $failing) {
            $processes->push(clone $process);
            $subcommand = $process->command[2];

            if (array_key_exists($subcommand, $failing)) {
                return value($failing[$subcommand], $process);
            }

            return match ($subcommand) {
                'unlock' => Process::result($sessionKey),
                'export' => Process::result(json_encode(['format' => $process->command[4]])),
                default => Process::result(),
            };
        });

        return $processes;
    }
}
