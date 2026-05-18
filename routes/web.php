<?php

use App\Http\Controllers\AccountPagesController;
use App\Http\Controllers\Admin\PromotionPagesController;
use App\Http\Controllers\Admin\TourPagesController;
use App\Http\Controllers\BookingPagesController;
use App\Http\Controllers\CatalogPagesController;
use App\Http\Controllers\NotificationPagesController;
use App\Http\Controllers\PublicTourPageController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SuperAdminTenantPageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('tours', [CatalogPagesController::class, 'index'])->name('catalog.index');
Route::get('tours/{slug}', [PublicTourPageController::class, 'show'])->name('tours.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('booking/new', [BookingPagesController::class, 'create'])->name('booking.new');
    Route::get('bookings/{bookingNumber}', [BookingPagesController::class, 'show'])->name('booking.show');

    Route::get('account', [AccountPagesController::class, 'profile'])->name('account.profile');
    Route::get('account/bookings', [AccountPagesController::class, 'bookings'])->name('account.bookings');
    Route::get('account/favorites', [AccountPagesController::class, 'favorites'])->name('account.favorites');
    Route::get('account/notifications', [NotificationPagesController::class, 'index'])->name('account.notifications');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('dashboard', 'Admin/Dashboard')->name('dashboard');
    Route::get('tours', [TourPagesController::class, 'index'])->name('tours.index');
    Route::get('tours/create', [TourPagesController::class, 'create'])->name('tours.create');
    Route::get('tours/{tour}/edit', [TourPagesController::class, 'edit'])->name('tours.edit');
    Route::get('promotions', [PromotionPagesController::class, 'index'])->name('promotions.index');
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
