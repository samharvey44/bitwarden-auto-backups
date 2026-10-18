<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The status shown after every reset link request.
     */
    private const string RESET_LINK_STATUS = 'If an account exists for that email address, we have emailed it a link to reset the password.';

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

        Notification::assertSentTo($user, QueuedResetPassword::class);
        $this->get('/forgot-password')->assertInertia(fn (Assert $page) => $page->where('status', self::RESET_LINK_STATUS));
    }

    public function test_sends_the_reset_email_from_the_queue_encrypted(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof QueuedResetPassword
            && $job->shouldBeEncrypted);
    }

    public function test_gives_the_same_response_for_an_email_address_without_an_account(): void
    {
        Notification::fake();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::RESET_LINK_STATUS);

        Notification::assertNothingSent();
    }

    public function test_gives_the_same_response_when_a_link_was_requested_recently(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::RESET_LINK_STATUS);

        Notification::assertSentToTimes($user, QueuedResetPassword::class, 1);
    }

    public function test_the_reset_email_shows_the_apps_own_logo_and_name(): void
    {
        config(['app.name' => 'Bitwarden Auto Backups']);
        $user = User::factory()->create();

        $html = (string) (new QueuedResetPassword('reset-token'))->toMail($user)->render();

        $this->assertStringContainsString(asset('icon-192.png'), $html);
        $this->assertStringContainsString('Bitwarden Auto Backups', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
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

        Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) use ($user): bool {
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
