<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AssignGuideController as AdminAssignGuideController;
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CancelTourDateController as AdminCancelTourDateController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\HotelController as AdminHotelController;
use App\Http\Controllers\Api\V1\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\V1\Admin\PaymentRefundController as AdminPaymentRefundController;
use App\Http\Controllers\Api\V1\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\RevenueReportController as AdminRevenueReportController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\RouteController as AdminRouteController;
use App\Http\Controllers\Api\V1\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\TenantConfigurationController as AdminTenantConfigurationController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TourController as AdminTourController;
use App\Http\Controllers\Api\V1\Admin\TourDateController as AdminTourDateController;
use App\Http\Controllers\Api\V1\Admin\TourDateIndexController as AdminTourDateIndexController;
use App\Http\Controllers\Api\V1\Admin\TourImageController as AdminTourImageController;
use App\Http\Controllers\Api\V1\Admin\TourStatusController as AdminTourStatusController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\GuideController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\Promotion\PromotionValidationController;
use App\Http\Controllers\Api\V1\PublicReviewController;
use App\Http\Controllers\Api\V1\PublicTourController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SuperAdmin\DashboardController as SuperAdminDashboardApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantConfigurationController as SuperAdminTenantConfigurationController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController as SuperAdminTenantApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantPlanController as SuperAdminTenantPlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantStatusController as SuperAdminTenantStatusController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantUserController as SuperAdminTenantUserController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('tenant', [TenantController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.v1.tenant.show');

Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('api.v1.newsletter.subscribe');
    Route::post('newsletter/unsubscribe', [NewsletterController::class, 'unsubscribeByToken'])->name('api.v1.newsletter.unsubscribe');
});

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('tours/categories', [CategoryController::class, 'index'])->name('api.v1.tours.categories.index');
    Route::get('tours', [CatalogController::class, 'index'])->name('api.v1.tours.index');
    Route::get('tours/{slug}', [PublicTourController::class, 'show'])->name('api.v1.tours.show');
    Route::get('tours/{slug}/reviews', [PublicReviewController::class, 'index'])->name('api.v1.tours.reviews.index');
});

Route::middleware('throttle:30,1')->group(function (): void {
    Route::post('bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
});

Route::middleware(['auth', 'tenant_member.only'])->group(function (): void {
    Route::post('promotions/validate', PromotionValidationController::class)
        ->name('api.v1.promotions.validate');
    Route::post('favorites', [FavoriteController::class, 'store'])->name('api.v1.favorites.store');
    Route::get('bookings/{bookingNumber}', [BookingController::class, 'show'])->name('api.v1.bookings.show');
    Route::put('bookings/{bookingNumber}/travelers', [BookingController::class, 'syncTravelers'])->name('api.v1.bookings.travelers.sync');

    Route::get('account/profile', [AccountController::class, 'profile'])->name('api.v1.account.profile');
    Route::put('account/profile', [AccountController::class, 'updateProfile'])->name('api.v1.account.profile.update');
    Route::get('account/bookings', [AccountController::class, 'bookings'])->name('api.v1.account.bookings');
    Route::get('account/favorites', [AccountController::class, 'favorites'])->name('api.v1.account.favorites');

    Route::post('reviews', [ReviewController::class, 'store'])->name('api.v1.reviews.store');

    Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.read-all');

    Route::post('bookings/{bookingNumber}/payments', [PaymentController::class, 'store'])->name('api.v1.bookings.payments.store');
});

Route::middleware(['auth', 'tenant_guide.only'])->group(function (): void {
    Route::get('guide/schedule', [GuideController::class, 'schedule'])->middleware('can:guide.schedule.view')->name('api.v1.guide.schedule');
    Route::get('guide/tour-dates/{tourDate}/travelers', [GuideController::class, 'travelers'])->middleware('can:guide.travelers.view')->name('api.v1.guide.tour-dates.travelers');
});

// WHY: `dashboard.view` es la llave del panel — la tiene admin, vendedor y operador, y no
// el guía (F018 spec.md §matriz). Sin ella el guía entraría a las pantallas de producto
// por sus permisos `tours.view`/`departures.view`, que existen para SUS salidas.
Route::middleware(['auth', 'tenant_admin.only', 'can:dashboard.view'])->prefix('admin')->name('api.v1.admin.')->group(function (): void {
    Route::put('tenant', [AdminTenantController::class, 'update'])->middleware('can:tenant.update')->name('tenant.update');
    Route::put('tenant/configuration', [AdminTenantConfigurationController::class, 'update'])->middleware('can:tenant.settings.update')->name('tenant.configuration.update');

    Route::get('dashboard', [AdminDashboardController::class, 'show'])->name('dashboard.show');
    Route::get('reports/revenue', AdminRevenueReportController::class)->middleware('can:reports.view')->name('reports.revenue');
    Route::get('bookings', [AdminBookingController::class, 'index'])->middleware('can:bookings.view')->name('bookings.index');

    Route::apiResource('tours', AdminTourController::class)
        ->names('tours')
        ->middlewareFor(['index', 'show'], 'can:tours.view')
        ->middlewareFor('store', 'can:tours.create')
        ->middlewareFor('update', 'can:tours.update')
        ->middlewareFor('destroy', 'can:tours.delete');
    Route::patch('tours/{tour}/status', AdminTourStatusController::class)->middleware('can:tours.publish')->name('tours.status');

    Route::get('tour-dates', AdminTourDateIndexController::class)->middleware('can:departures.view')->name('tour-dates.index');
    Route::get('tours/{tour}/dates', [AdminTourDateController::class, 'index'])->middleware('can:departures.view')->name('tours.dates.index');
    Route::post('tours/{tour}/dates', [AdminTourDateController::class, 'store'])->middleware('can:departures.create')->name('tours.dates.store');
    Route::put('tour-dates/{tourDate}', [AdminTourDateController::class, 'update'])->middleware('can:departures.update')->name('tour-dates.update');
    Route::patch('tour-dates/{tourDate}/cancel', AdminCancelTourDateController::class)->middleware('can:departures.cancel')->name('tour-dates.cancel');
    Route::delete('tour-dates/{tourDate}', [AdminTourDateController::class, 'destroy'])->middleware('can:departures.delete')->name('tour-dates.destroy');
    Route::patch('tour-dates/{tourDate}/guide', AdminAssignGuideController::class)->middleware('can:departures.assign_guide')->name('tour-dates.guide');

    Route::apiResource('routes', AdminRouteController::class)->only(['index', 'store', 'update', 'destroy'])->names('routes')
        ->middlewareFor('index', 'can:logistics.view')
        ->middlewareFor(['store', 'update', 'destroy'], 'can:logistics.manage');
    Route::apiResource('providers', AdminProviderController::class)->only(['index', 'store', 'update', 'destroy'])->names('providers')
        ->middlewareFor('index', 'can:logistics.view')
        ->middlewareFor(['store', 'update', 'destroy'], 'can:logistics.manage');
    Route::apiResource('hotels', AdminHotelController::class)->only(['index', 'store', 'update', 'destroy'])->names('hotels')
        ->middlewareFor('index', 'can:logistics.view')
        ->middlewareFor(['store', 'update', 'destroy'], 'can:logistics.manage');

    Route::middleware('can:tours.images.manage')->group(function (): void {
        Route::post('tours/{tour}/images', [AdminTourImageController::class, 'store'])->name('tours.images.store');
        Route::patch('tours/{tour}/images/{image}', [AdminTourImageController::class, 'update'])->name('tours.images.update');
        Route::delete('tours/{tour}/images/{image}', [AdminTourImageController::class, 'destroy'])->name('tours.images.destroy');
    });

    Route::apiResource('promotions', AdminPromotionController::class)
        ->names('promotions')
        ->middlewareFor(['index', 'show'], 'can:promotions.view')
        ->middlewareFor('store', 'can:promotions.create')
        ->middlewareFor('update', 'can:promotions.update')
        ->middlewareFor('destroy', 'can:promotions.delete');

    Route::get('reviews', [AdminReviewController::class, 'index'])->middleware('can:reviews.view')->name('reviews.index');
    Route::patch('reviews/{review}/status', [AdminReviewController::class, 'updateStatus'])->middleware('can:reviews.moderate')->name('reviews.status');
    Route::post('reviews/{review}/respond', [AdminReviewController::class, 'respond'])->middleware('can:reviews.respond')->name('reviews.respond');

    Route::post('payments/{payment}/refund', AdminPaymentRefundController::class)->middleware('can:payments.refund')->name('payments.refund');

    Route::get('newsletter/subscribers', [AdminNewsletterController::class, 'index'])->middleware('can:newsletter.view')->name('newsletter.subscribers');
    Route::post('newsletter/send', [AdminNewsletterController::class, 'send'])->middleware('can:newsletter.send')->name('newsletter.send');

    Route::get('users', [AdminTeamController::class, 'index'])->middleware('can:team.view')->name('users.index');
    Route::post('users', [AdminTeamController::class, 'store'])->middleware('can:team.invite')->name('users.store');
    Route::patch('users/{user}/role', [AdminTeamController::class, 'updateRole'])->middleware('can:team.role.update')->name('users.role');
    Route::patch('users/{user}/suspend', [AdminTeamController::class, 'suspend'])->middleware('can:team.suspend')->name('users.suspend');
    Route::patch('users/{user}/reactivate', [AdminTeamController::class, 'reactivate'])->middleware('can:team.suspend')->name('users.reactivate');
});

// WHY: super-admin API routes intentionally do NOT use Route::domain().
// The super_admin.only middleware enforces the role; pinning to a specific
// host would force Wayfinder to emit absolute URLs with that host (which
// breaks ports in dev). The Inertia /super-admin pages in routes/web.php
// remain Route::domain-gated so the URLs the user navigates to stay correct.
Route::middleware(['auth', 'super_admin.only'])
    ->prefix('super-admin')
    ->name('api.v1.super-admin.')
    ->group(function (): void {
        Route::get('dashboard', [SuperAdminDashboardApiController::class, 'show'])->name('dashboard.show');
        Route::get('tenants', [SuperAdminTenantApiController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [SuperAdminTenantApiController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [SuperAdminTenantApiController::class, 'show'])->name('tenants.show');
        Route::post('tenants/{tenant}/users', [SuperAdminTenantUserController::class, 'store'])->name('tenants.users.store');
        Route::patch('tenants/{tenant}/status', [SuperAdminTenantStatusController::class, 'update'])->name('tenants.status.update');
        Route::patch('tenants/{tenant}/plan', [SuperAdminTenantPlanController::class, 'update'])->name('tenants.plan.update');
        Route::post('tenants/{tenant}/configuration', [SuperAdminTenantConfigurationController::class, 'update'])->name('tenants.configuration.update');
    });
