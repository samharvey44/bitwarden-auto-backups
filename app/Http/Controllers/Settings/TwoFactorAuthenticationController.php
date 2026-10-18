<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    /**
     * Show the user's two-factor authentication settings.
     *
     * Fortify's two-factor endpoints require a recently confirmed password,
     * so the page itself asks for it first. Two-factor authentication is
     * awaiting confirmation once it has been enabled but the user has not yet
     * entered a code from their authenticator app.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/TwoFactor', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'awaitingConfirmation' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
        ]);
    }
}
