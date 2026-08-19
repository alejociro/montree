<?php

use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

// WHY: fuera de `auth`. El selector de idioma vive tambien en el sitio publico del
// tenant y en las pantallas de login, donde todavia no hay sesion.
Route::patch('locale', LocaleController::class)->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/password/setup', [SecurityController::class, 'setup'])
        ->middleware('throttle:6,1')
        ->name('user-password.setup');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
