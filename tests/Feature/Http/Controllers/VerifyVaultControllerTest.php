<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use App\Services\Bitwarden\Exceptions\BitwardenCliException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class VerifyVaultControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reports_that_working_credentials_work(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create(['name' => 'Personal']);
        $this->mock(BitwardenCli::class, function (MockInterface $mock) use ($vault): void {
            $mock->shouldReceive('verifyCredentials')->once()->withArgs(fn (Vault $verified): bool => $verified->is($vault));
        });

        $this->actingAs($user)
            ->from("/vaults/{$vault->id}")
            ->post("/vaults/{$vault->id}/verify")
            ->assertRedirect("/vaults/{$vault->id}")
            ->assertInertiaFlash('success', 'The credentials for Personal work.');
    }

    public function test_reports_why_the_credentials_failed(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyCredentials')->once()->andThrow(new BitwardenCliException('Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.'));
        });

        $this->actingAs($user)
            ->post("/vaults/{$vault->id}/verify")
            ->assertInertiaFlash('error', 'Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.')
            ->assertInertiaFlashMissing('success');
    }

    public function test_cannot_check_another_users_vault(): void
    {
        $vault = Vault::factory()->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('verifyCredentials');
        });

        $this->actingAs(User::factory()->create())->post("/vaults/{$vault->id}/verify")->assertNotFound();
    }

    public function test_is_throttled_to_six_checks_a_minute(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $this->mock(BitwardenCli::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyCredentials')->times(6);
        });

        foreach (range(1, 6) as $check) {
            $this->actingAs($user)->post("/vaults/{$vault->id}/verify")->assertRedirect();
        }

        $this->actingAs($user)->post("/vaults/{$vault->id}/verify")->assertTooManyRequests();
    }
}
