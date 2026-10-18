<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Registration is disabled, so users are only created with the
     * users:create command.
     */
    public function boot(): void
    {
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerViews();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
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
        Fortify::loginView(fn (): Response => Inertia::render('auth/Login'));

        Fortify::requestPasswordResetLinkView(fn (): Response => Inertia::render('auth/ForgotPassword'));

        Fortify::resetPasswordView(fn (Request $request): Response => Inertia::render('auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
        ]));

        Fortify::twoFactorChallengeView(fn (): Response => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn (): Response => Inertia::render('auth/ConfirmPassword'));
    }
}
