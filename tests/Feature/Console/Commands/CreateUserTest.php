<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prompts_for_the_users_details_and_creates_them(): void
    {
        $this->artisan('users:create')
            ->expectsQuestion('Name', 'Sam')
            ->expectsQuestion('Email address', 'sam@example.com')
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Enter the password again to confirm', 'correct-horse')
            ->expectsOutputToContain('User [sam@example.com] created.')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame('Sam', $user->name);
        $this->assertSame('sam@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse', $user->password));
    }

    public function test_creates_the_user_without_prompting_from_options_and_the_environment(): void
    {
        $this->withProcessEnvironment(['USER_PASSWORD' => 'correct-horse']);

        $this->artisan('users:create', ['--name' => 'Sam', '--email' => 'sam@example.com', '--no-interaction' => true])->assertSuccessful();

        $this->assertTrue(Hash::check('correct-horse', User::sole()->password));
    }

    public function test_reports_every_missing_detail_without_prompting(): void
    {
        $this->artisan('users:create', ['--no-interaction' => true])
            ->expectsOutputToContain('The name is required. Pass it with --name.')
            ->expectsOutputToContain('The email address is required. Pass it with --email.')
            ->expectsOutputToContain('The password is required. Set it in the USER_PASSWORD environment variable.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_an_email_address_that_is_taken_and_a_short_password(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);
        $this->withProcessEnvironment(['USER_PASSWORD' => 'short']);

        $this->artisan('users:create', ['--name' => 'Sam', '--email' => 'sam@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('The email has already been taken.')
            ->expectsOutputToContain('The password field must be at least 8 characters.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_rejects_a_password_that_is_not_confirmed(): void
    {
        $this->artisan('users:create', ['--name' => 'Sam', '--email' => 'sam@example.com'])
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Enter the password again to confirm', 'correct-hrose')
            ->expectsOutputToContain('The passwords do not match.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
