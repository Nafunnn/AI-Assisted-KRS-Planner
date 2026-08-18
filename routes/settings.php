<?php

use App\Http\Controllers\Settings\AiProviderConfigController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/ai-providers', [AiProviderConfigController::class, 'edit'])->name('ai-providers.edit');
    Route::post('settings/ai-providers', [AiProviderConfigController::class, 'store'])->name('ai-providers.store');
    Route::patch('settings/ai-providers/{aiProviderConfig}/activate', [AiProviderConfigController::class, 'activate'])->name('ai-providers.activate');
    Route::delete('settings/ai-providers/{aiProviderConfig}', [AiProviderConfigController::class, 'destroy'])->name('ai-providers.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
