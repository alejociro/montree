<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\TenantConfigurationController as AdminTenantConfigurationController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('tenant', [TenantController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.v1.tenant.show');

Route::middleware(['auth'])->prefix('admin')->name('api.v1.admin.')->group(function (): void {
    Route::put('tenant', [AdminTenantController::class, 'update'])->name('tenant.update');
    Route::put('tenant/configuration', [AdminTenantConfigurationController::class, 'update'])->name('tenant.configuration.update');
});
