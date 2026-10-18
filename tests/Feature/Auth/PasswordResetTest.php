<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_the_forgot_password_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
    }

    public function test_emails_a_reset_link_and_shows_the_status(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->get('/forgot-password')->assertInertia(fn (Assert $page) => $page->where('status', 'We have emailed your password reset link.'));
    }

    public function test_shows_the_reset_page_with_the_token_and_email_address(): void
    {
        $this->get('/reset-password/reset-token?email=sam%40example.com')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/ResetPassword')
                ->where('token', 'reset-token')
                ->where('email', 'sam@example.com'));
    }

    public function test_resets_the_password_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])->assertRedirect('/login')->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('a-new-password', $user->refresh()->password));
    }
}
