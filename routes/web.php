<?php

use App\Http\Controllers\Auth\CancelTwoFactorChallengeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DownloadBackupFileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VerifyVaultController;
use Illuminate\Support\Facades\Route;

Route::post('two-factor-challenge/cancel', CancelTwoFactorChallengeController::class)
    ->middleware('guest')
    ->name('two-factor.login.cancel');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/vaults')->name('home');

    Route::prefix('vaults')->name('vaults.')->group(function () {
        Route::get('/', [VaultController::class, 'index'])->name('index');
        Route::get('create', [VaultController::class, 'create'])->name('create');
        Route::post('/', [VaultController::class, 'store'])->name('store');

        Route::prefix('{vault}')->group(function () {
            Route::get('/', [VaultController::class, 'show'])->name('show');
            Route::get('edit', [VaultController::class, 'edit'])->name('edit');
            Route::patch('/', [VaultController::class, 'update'])->name('update');
            Route::delete('/', [VaultController::class, 'destroy'])->name('destroy');

            // Each check logs in to Bitwarden, so it is throttled to avoid tripping its rate limits.
            Route::post('verify', VerifyVaultController::class)
                ->middleware('throttle:6,1')
                ->name('verify');

            Route::post('backups', [BackupController::class, 'store'])->name('backups.store');

            Route::get('backup-files/{backupFile}/download', DownloadBackupFileController::class)->name('backup-files.download');
        });
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/settings/profile');
        Route::inertia('profile', 'settings/Profile')->name('profile');
        Route::inertia('password', 'settings/Password')->name('password');
        Route::get('two-factor', TwoFactorAuthenticationController::class)
            ->middleware('password.confirm')
            ->name('two-factor');
    });
});
