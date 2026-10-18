<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
    }

    /**
     * Bootstrap any application services.
     *
     * Registration is only usable when REGISTRATION_ENABLED is true. Otherwise
     * users are only created with the users:create command.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerViews();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    /**
     * Render Fortify's authentication screens as Inertia pages.
     */
    private function registerViews(): void
    {
        Fortify::loginView(fn (): Response => Inertia::render('auth/Login', [
            'canRegister' => config('fortify.registration_enabled'),
        ]));

        Fortify::registerView(fn (): Response => Inertia::render('auth/Register'));

        Fortify::requestPasswordResetLinkView(fn (): Response => Inertia::render('auth/ForgotPassword'));

        Fortify::resetPasswordView(fn (Request $request): Response => Inertia::render('auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
        ]));

        Fortify::twoFactorChallengeView(fn (): Response => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn (): Response => Inertia::render('auth/ConfirmPassword'));
    }
}
