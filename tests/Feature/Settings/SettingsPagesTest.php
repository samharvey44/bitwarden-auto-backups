<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('settingsPagesDataProvider')]
    public function test_guests_are_sent_to_the_login_page(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_shows_the_profile_and_password_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->component('settings/Profile'));
        $this->get('/settings/password')->assertInertia(fn (Assert $page) => $page->component('settings/Password'));
    }

    public function test_updates_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings/password')
            ->put('/user/password', [
                'current_password' => 'password',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])
            ->assertRedirect('/settings/password')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-password', $user->refresh()->password));
    }

    public function test_changing_the_password_keeps_this_session_logged_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings/password')->assertOk();

        $this->put('/user/password', [
            'current_password' => 'password',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertSessionHasNoErrors();

        $this->get('/vaults')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_sessions_from_before_a_password_change_are_logged_out(): void
    {
        $user = User::factory()->create();
        $hashBeforeTheChange = Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword());
        $user->forceFill(['password' => 'changed-on-another-device'])->save();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $hashBeforeTheChange])
            ->get('/vaults')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_the_two_factor_page_asks_for_the_password_first(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings/two-factor')->assertRedirect('/user/confirm-password');

        $this->get('/user/confirm-password')->assertInertia(fn (Assert $page) => $page->component('auth/ConfirmPassword'));
    }

    public function test_the_two_factor_page_shows_whether_it_is_off_awaiting_confirmation_or_on(): void
    {
        $this->assertTwoFactorPage(User::factory()->create(), enabled: false, awaitingConfirmation: false);
        $this->assertTwoFactorPage(User::factory()->awaitingTwoFactorConfirmation()->create(), enabled: false, awaitingConfirmation: true);
        $this->assertTwoFactorPage(User::factory()->withTwoFactorAuthentication()->create(), enabled: true, awaitingConfirmation: false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function settingsPagesDataProvider(): array
    {
        return [
            'profile' => ['/settings/profile'],
            'password' => ['/settings/password'],
            'two-factor authentication' => ['/settings/two-factor'],
        ];
    }

    /**
     * Visit the two-factor settings page as the user, with their password
     * recently confirmed, and check the state it is shown in.
     */
    private function assertTwoFactorPage(User $user, bool $enabled, bool $awaitingConfirmation): void
    {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/two-factor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/TwoFactor')
                ->where('enabled', $enabled)
                ->where('awaitingConfirmation', $awaitingConfirmation));
    }
}
