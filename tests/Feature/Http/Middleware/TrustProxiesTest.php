<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_builds_https_links_when_the_local_proxy_forwards_https(): void
    {
        $response = $this->get('/vaults', ['X-Forwarded-Proto' => 'https']);

        $response->assertRedirect('https://localhost/login');
    }

    public function test_ignores_the_host_forwarded_by_the_local_proxy(): void
    {
        $response = $this->get('/vaults', ['X-Forwarded-Host' => 'attacker.example']);

        $response->assertRedirect('http://localhost/login');
    }

    public function test_ignores_https_forwarded_from_other_addresses(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('/vaults', ['X-Forwarded-Proto' => 'https']);

        $response->assertRedirect('http://localhost/login');
    }

    public function test_rate_limits_logins_by_the_visitor_address_the_local_proxy_forwards(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'], ['X-Forwarded-For' => '203.0.113.1']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password'], ['X-Forwarded-For' => '203.0.113.2']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_rate_limits_logins_from_other_addresses_whatever_visitor_address_they_forward(): void
    {
        $user = User::factory()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'], ['X-Forwarded-For' => "203.0.113.{$attempt}"]);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password'], ['X-Forwarded-For' => '203.0.113.6']);

        $response->assertTooManyRequests();
        $this->assertGuest();
    }
}
