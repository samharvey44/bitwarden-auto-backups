<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_shows_the_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_users_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_log_in_with_the_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logging_in_is_rate_limited_per_email_address(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_users_with_two_factor_authentication_must_enter_a_code(): void
    {
        $user = User::factory()->withTwoFactorAuthentication()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
    }

    public function test_users_who_have_not_confirmed_two_factor_authentication_are_not_challenged(): void
    {
        $user = User::factory()->awaitingTwoFactorConfirmation()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_finish_logging_in_with_a_recovery_code(): void
    {
        $user = User::factory()->withTwoFactorAuthentication()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_login_waiting_for_a_two_factor_code_can_be_cancelled(): void
    {
        $user = User::factory()->withTwoFactorAuthentication()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge/cancel')
            ->assertRedirect('/login')
            ->assertSessionMissing('login.id')
            ->assertSessionMissing('login.remember');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1']);
        $this->assertGuest();
    }

    public function test_signed_in_users_cannot_cancel_a_two_factor_challenge(): void
    {
        $this->actingAs(User::factory()->create())->post('/two-factor-challenge/cancel')->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }
}
