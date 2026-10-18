<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_is_disabled_by_default(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registration())->assertNotFound();

        $this->assertDatabaseCount('users', 0);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
    }

    public function test_shows_the_register_page_and_links_to_it_when_enabled(): void
    {
        config(['fortify.registration_enabled' => true]);

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
    }

    public function test_creates_an_account_and_logs_in_when_enabled(): void
    {
        config(['fortify.registration_enabled' => true]);

        $this->post('/register', $this->registration())->assertRedirect('/');

        $user = User::sole();
        $this->assertSame('Sam', $user->name);
        $this->assertSame('sam@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_limits_registrations_to_five_an_hour_from_each_address(): void
    {
        config(['fortify.registration_enabled' => true]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/register', $this->registration(['email' => "sam{$attempt}@example.com"]));
            auth()->logout();
        }

        $this->post('/register', $this->registration(['email' => 'sam6@example.com']))->assertTooManyRequests();

        $this->assertDatabaseCount('users', 5);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidRegistrationsDataProvider')]
    public function test_rejects_invalid_registrations(array $overrides, string $field): void
    {
        config(['fortify.registration_enabled' => true]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->registration($overrides))->assertSessionHasErrors($field);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidRegistrationsDataProvider(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'email address already taken' => [['email' => 'taken@example.com'], 'email'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'unconfirmed password' => [['password_confirmation' => 'correct-hrose'], 'password'],
        ];
    }

    /**
     * Get a registration that passes validation.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
            ...$overrides,
        ];
    }
}
