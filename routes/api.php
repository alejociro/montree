<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AssignGuideController as AdminAssignGuideController;
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\V1\Admin\PaymentRefundController as AdminPaymentRefundController;
use App\Http\Controllers\Api\V1\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\V1\Admin\RevenueReportController as AdminRevenueReportController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\TenantConfigurationController as AdminTenantConfigurationController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TourController as AdminTourController;
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
use App\Http\Controllers\Api\V1\TenantController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('tenant', [TenantController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.v1.tenant.show');

Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('api.v1.newsletter.subscribe');
    Route::post('newsletter/unsubscribe', [NewsletterController::class, 'unsubscribeByToken'])->name('api.v1.newsletter.unsubscribe');
});

// TEMP DEBUG — remove after diagnosing auth.
Route::middleware(['auth'])->get('_debug/me_auth', function (Request $request) {
    setPermissionsTeamId(0);
    $user = $request->user();
    $user?->unsetRelation('roles');

    return response()->json([
        'reached' => true,
        'auth_user_id' => $user?->id,
        'is_super_admin' => $user?->hasRole(UserRole::SuperAdmin->value) ?? false,
    ]);
});

Route::middleware(['auth', 'super_admin.only'])->get('_debug/me_super', function (Request $request) {
    return response()->json(['reached' => true, 'host' => $request->getHost()]);
});

Route::domain((string) config('montree.super_admin_host'))
    ->middleware(['auth', 'super_admin.only'])
    ->get('_debug/me_super_domain', function (Request $request) {
        return response()->json(['reached' => true, 'host' => $request->getHost()]);
    });

Route::get('_debug/me', function (Request $request) {
    setPermissionsTeamId(0);
    $user = $request->user();
    $user?->unsetRelation('roles');

    return response()->json([
        'host' => $request->getHost(),
        'session_id' => $request->session()->getId(),
        'session_cookie_name' => config('session.cookie'),
        'session_domain' => config('session.domain'),
        'cookies_received' => array_keys($request->cookies->all()),
        'auth_user_id' => $user?->id,
        'auth_user_email' => $user?->email,
        'is_super_admin' => $user?->hasRole(UserRole::SuperAdmin->value) ?? false,
        'tenant' => Tenant::current()?->slug,
    ]);
});

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('tours/categories', [CategoryController::class, 'index'])->name('api.v1.tours.categories.index');
    Route::get('tours', [CatalogController::class, 'index'])->name('api.v1.tours.index');
    Route::get('tours/{slug}', [PublicTourController::class, 'show'])->name('api.v1.tours.show');
    Route::get('tours/{slug}/reviews', [PublicReviewController::class, 'index'])->name('api.v1.tours.reviews.index');
});

Route::middleware(['auth'])->group(function (): void {
    Route::post('promotions/validate', PromotionValidationController::class)
        ->name('api.v1.promotions.validate');
    Route::post('favorites', [FavoriteController::class, 'store'])->name('api.v1.favorites.store');
    Route::post('bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
    Route::get('bookings/{bookingNumber}', [BookingController::class, 'show'])->name('api.v1.bookings.show');

    Route::get('account/profile', [AccountController::class, 'profile'])->name('api.v1.account.profile');
    Route::put('account/profile', [AccountController::class, 'updateProfile'])->name('api.v1.account.profile.update');
    Route::get('account/bookings', [AccountController::class, 'bookings'])->name('api.v1.account.bookings');
    Route::get('account/favorites', [AccountController::class, 'favorites'])->name('api.v1.account.favorites');

    Route::post('reviews', [ReviewController::class, 'store'])->name('api.v1.reviews.store');

    Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.read-all');

    Route::post('bookings/{bookingNumber}/payments', [PaymentController::class, 'store'])->name('api.v1.bookings.payments.store');

    Route::get('guide/schedule', [GuideController::class, 'schedule'])->name('api.v1.guide.schedule');
    Route::get('guide/tour-dates/{tourDate}/travelers', [GuideController::class, 'travelers'])->name('api.v1.guide.tour-dates.travelers');
});

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

    Route::apiResource('promotions', AdminPromotionController::class)->names('promotions');

    Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/status', [AdminReviewController::class, 'updateStatus'])->name('reviews.status');
    Route::post('reviews/{review}/respond', [AdminReviewController::class, 'respond'])->name('reviews.respond');

    Route::post('payments/{payment}/refund', AdminPaymentRefundController::class)->name('payments.refund');

    Route::get('newsletter/subscribers', [AdminNewsletterController::class, 'index'])->name('newsletter.subscribers');
    Route::post('newsletter/send', [AdminNewsletterController::class, 'send'])->name('newsletter.send');

    Route::get('users', [AdminTeamController::class, 'index'])->name('users.index');
    Route::post('users', [AdminTeamController::class, 'store'])->name('users.store');
    Route::patch('users/{user}/role', [AdminTeamController::class, 'updateRole'])->name('users.role');
    Route::patch('users/{user}/suspend', [AdminTeamController::class, 'suspend'])->name('users.suspend');
    Route::patch('users/{user}/reactivate', [AdminTeamController::class, 'reactivate'])->name('users.reactivate');
    Route::patch('tour-dates/{tourDate}/guide', AdminAssignGuideController::class)->name('tour-dates.guide');
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
        Route::get('tenants/{tenant}', [SuperAdminTenantApiController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [SuperAdminTenantStatusController::class, 'update'])->name('tenants.status.update');
        Route::patch('tenants/{tenant}/plan', [SuperAdminTenantPlanController::class, 'update'])->name('tenants.plan.update');
        Route::post('tenants/{tenant}/configuration', [SuperAdminTenantConfigurationController::class, 'update'])->name('tenants.configuration.update');
    });
