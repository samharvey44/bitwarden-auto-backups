<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class VerifyVaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reports_that_working_credentials_work(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);
        $this->mock(BitwardenCli::class, function (MockInterface $mock) use ($vault): void {
            $mock->shouldReceive('verifyCredentials')->once()->withArgs(fn (Vault $verified): bool => $verified->is($vault));
        });

        $this->artisan('vaults:verify', ['vault' => $vault->id])
            ->expectsOutputToContain('The credentials for vault [Personal] work.')
            ->assertSuccessful();
    }

    public function test_reports_why_the_credentials_failed(): void
    {
        $vault = Vault::factory()->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyCredentials')->once()->andThrow(new BitwardenCliException('Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.'));
        });

        $this->artisan('vaults:verify', ['vault' => $vault->id])
            ->expectsOutputToContain('Invalid master password.')
            ->expectsOutputToContain("vaults:edit {$vault->id}")
            ->assertFailed();
    }

    public function test_fails_for_a_vault_that_does_not_exist(): void
    {
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('verifyCredentials');
        });

        $this->artisan('vaults:verify', ['vault' => 999])
            ->expectsOutputToContain('Vault [999] does not exist.')
            ->assertFailed();
    }
}
