<?php

use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SuperAdminTenantPageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::domain((string) config('montree.super_admin_host'))
    ->middleware(['auth', 'super_admin.only'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');
        Route::get('tenants', [SuperAdminTenantPageController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [SuperAdminTenantPageController::class, 'show'])->name('tenants.show');
    });

require __DIR__.'/settings.php';
