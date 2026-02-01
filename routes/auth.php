<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasskeyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/login')->name('auth.login');

Route::prefix('oauth')->group(function () {
    Route::get('/redirect/{driver}', [OAuthController::class, 'redirect'])->name('auth.oauth.redirect');
    Route::get('/callback/{driver}', [OAuthController::class, 'callback'])->name('auth.oauth.callback')->withoutMiddleware('guest');
});

// Passkeys routes - WebAuthn endpoints for passkey registration and authentication
Route::post('/passkeys/register/options', [PasskeyController::class, 'registerOptions'])
    ->middleware('auth')
    ->name('passkeys.register.options');

Route::post('/passkeys/register', [PasskeyController::class, 'register'])
    ->middleware('auth')
    ->name('passkeys.register');

Route::post('/passkeys/authenticate/options', [PasskeyController::class, 'authenticateOptions'])
    ->name('passkeys.authenticate.options');

Route::post('/passkeys/authenticate', [PasskeyController::class, 'authenticate'])
    ->name('passkeys.authenticate');
