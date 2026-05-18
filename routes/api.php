<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\RevenueReportController as AdminRevenueReportController;
use App\Http\Controllers\Api\V1\Admin\TenantConfigurationController as AdminTenantConfigurationController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TourController as AdminTourController;
use App\Http\Controllers\Api\V1\Admin\TourImageController as AdminTourImageController;
use App\Http\Controllers\Api\V1\Admin\TourStatusController as AdminTourStatusController;
use App\Http\Controllers\Api\V1\SuperAdmin\DashboardController as SuperAdminDashboardApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController as SuperAdminTenantApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantPlanController as SuperAdminTenantPlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantStatusController as SuperAdminTenantStatusController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('tenant', [TenantController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.v1.tenant.show');

Route::middleware(['auth'])->prefix('admin')->name('api.v1.admin.')->group(function (): void {
    Route::put('tenant', [AdminTenantController::class, 'update'])->name('tenant.update');
    Route::put('tenant/configuration', [AdminTenantConfigurationController::class, 'update'])->name('tenant.configuration.update');

    Route::get('dashboard', [AdminDashboardController::class, 'show'])->name('dashboard.show');
    Route::get('reports/revenue', AdminRevenueReportController::class)->name('reports.revenue');
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');

    Route::apiResource('tours', AdminTourController::class)->names('tours');
    Route::patch('tours/{tour}/status', AdminTourStatusController::class)->name('tours.status');
    Route::post('tours/{tour}/images', [AdminTourImageController::class, 'store'])->name('tours.images.store');
    Route::patch('tours/{tour}/images/{image}', [AdminTourImageController::class, 'update'])->name('tours.images.update');
    Route::delete('tours/{tour}/images/{image}', [AdminTourImageController::class, 'destroy'])->name('tours.images.destroy');
});

Route::domain((string) config('montree.super_admin_host'))
    ->middleware(['auth', 'super_admin.only'])
    ->prefix('super-admin')
    ->name('api.v1.super-admin.')
    ->group(function (): void {
        Route::get('dashboard', [SuperAdminDashboardApiController::class, 'show'])->name('dashboard.show');
        Route::get('tenants', [SuperAdminTenantApiController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [SuperAdminTenantApiController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [SuperAdminTenantStatusController::class, 'update'])->name('tenants.status.update');
        Route::patch('tenants/{tenant}/plan', [SuperAdminTenantPlanController::class, 'update'])->name('tenants.plan.update');
    });
