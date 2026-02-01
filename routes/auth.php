<?php

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/login')->name('auth.login');

Route::prefix('oauth')->group(function () {
    Route::get('/redirect/{driver}', [OAuthController::class, 'redirect'])->name('auth.oauth.redirect');
    Route::get('/callback/{driver}', [OAuthController::class, 'callback'])->name('auth.oauth.callback')->withoutMiddleware('guest');
});

// Passkeys routes - will be registered when spatie/laravel-passkeys is installed
// These routes provide WebAuthn endpoints for passkey registration and authentication
if (class_exists('Spatie\LaravelPasskeys\Http\Controllers\PasskeyController')) {
    Route::post('/passkeys/register/options', [\Spatie\LaravelPasskeys\Http\Controllers\PasskeyController::class, 'registerOptions'])
        ->middleware('auth')
        ->name('passkeys.register.options');
    
    Route::post('/passkeys/register', [\Spatie\LaravelPasskeys\Http\Controllers\PasskeyController::class, 'register'])
        ->middleware('auth')
        ->name('passkeys.register');
    
    Route::post('/passkeys/authenticate/options', [\Spatie\LaravelPasskeys\Http\Controllers\PasskeyController::class, 'authenticateOptions'])
        ->name('passkeys.authenticate.options');
    
    Route::post('/passkeys/authenticate', [\Spatie\LaravelPasskeys\Http\Controllers\PasskeyController::class, 'authenticate'])
        ->name('passkeys.authenticate');
}

