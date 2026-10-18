<?php

use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VerifyVaultController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/vaults')->name('home');

    Route::resource('vaults', VaultController::class);

    // Each check logs in to Bitwarden, so it is throttled to avoid tripping its rate limits.
    Route::post('vaults/{vault}/verify', VerifyVaultController::class)
        ->middleware('throttle:6,1')
        ->name('vaults.verify');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/settings/profile');
        Route::inertia('profile', 'settings/Profile')->name('profile');
        Route::inertia('password', 'settings/Password')->name('password');
        Route::get('two-factor', TwoFactorAuthenticationController::class)
            ->middleware('password.confirm')
            ->name('two-factor');
    });
});
