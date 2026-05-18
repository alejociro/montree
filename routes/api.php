<?php

declare(strict_types=1);

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
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController as SuperAdminTenantApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantPlanController as SuperAdminTenantPlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantStatusController as SuperAdminTenantStatusController;
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
