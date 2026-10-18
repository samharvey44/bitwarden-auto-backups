<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelTwoFactorChallengeController extends Controller
{
    /**
     * Abandon a login that is waiting for a two-factor code, so someone without
     * their codes can start again or log in as someone else.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->forget(['login.id', 'login.remember']);

        return to_route('login');
    }
}
