<?php

use App\Http\Controllers\AccountPagesController;
use App\Http\Controllers\Admin\PromotionPagesController;
use App\Http\Controllers\Admin\ReviewPagesController;
use App\Http\Controllers\Admin\TeamPagesController;
use App\Http\Controllers\Admin\TourPagesController;
use App\Http\Controllers\Auth\CrossHostLoginController;
use App\Http\Controllers\BookingPagesController;
use App\Http\Controllers\CatalogPagesController;
use App\Http\Controllers\Guide\GuidePagesController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\NewsletterPagesController;
use App\Http\Controllers\NotificationPagesController;
use App\Http\Controllers\Onboarding\ClaimAgencyController;
use App\Http\Controllers\Onboarding\OnboardingPageController;
use App\Http\Controllers\Onboarding\ResendVerificationController;
use App\Http\Controllers\Onboarding\VerifyAgencyController;
use App\Http\Controllers\PublicTourPageController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SuperAdminTenantPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePageController::class)->name('home');

Route::get('tours', [CatalogPagesController::class, 'index'])->name('catalog.index');
Route::get('tours/{slug}', [PublicTourPageController::class, 'show'])->name('tours.show');
Route::get('unsubscribe/{token}', [NewsletterPagesController::class, 'unsubscribe'])->name('newsletter.unsubscribe.page');

// WHY: cross-host login handoff (isolated per-subdomain sessions, see §10). Public
// by design — the single-use token IS the credential. Logs the user in on this host.
Route::get('auth/handoff/{token}', CrossHostLoginController::class)
    ->middleware('throttle:10,1')
    ->name('auth.handoff');

Route::get('booking/new', [BookingPagesController::class, 'create'])->name('booking.new');

// WHY: self-serve onboarding (F016). `/start` + check-email + verify run on the
// platform host; `claim` runs on the tenant subdomain and produces the founder's
// host-scoped session. verify/claim are gated by signed URLs (the signature is the
// credential); onboarding.claim additionally consumes a one-shot nonce.
Route::get('start', [OnboardingPageController::class, 'start'])->name('onboarding.start');
Route::get('onboarding/check-email', [OnboardingPageController::class, 'checkEmail'])->name('onboarding.check-email');
Route::get('onboarding/verify/{tenant}/{user}', VerifyAgencyController::class)
    ->middleware('signed')
    ->name('onboarding.verify');
Route::get('onboarding/claim', ClaimAgencyController::class)
    ->middleware('signed')
    ->name('onboarding.claim');
Route::post('onboarding/resend-verification', ResendVerificationController::class)
    ->middleware('throttle:3,10')
    ->name('onboarding.resend-verification');

Route::middleware(['auth', 'verified', 'tenant_member.only'])->group(function () {
    Route::get('dashboard', fn () => redirect('/account/bookings'))->name('dashboard');
    Route::get('bookings/{bookingNumber}', [BookingPagesController::class, 'show'])->name('booking.show');

    Route::get('account', [AccountPagesController::class, 'profile'])->name('account.profile');
    Route::get('account/bookings', [AccountPagesController::class, 'bookings'])->name('account.bookings');
    Route::get('account/favorites', [AccountPagesController::class, 'favorites'])->name('account.favorites');
    Route::get('account/notifications', [NotificationPagesController::class, 'index'])->name('account.notifications');
    Route::get('account/bookings/{bookingNumber}/review', [AccountPagesController::class, 'review'])->name('account.bookings.review');
});

Route::middleware(['auth', 'verified', 'tenant_admin.only'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('dashboard', 'Admin/Dashboard')->name('dashboard');
    Route::get('tours', [TourPagesController::class, 'index'])->name('tours.index');
    Route::get('tours/create', [TourPagesController::class, 'create'])->name('tours.create');
    Route::get('tours/{tour}/edit', [TourPagesController::class, 'edit'])->name('tours.edit');
    Route::get('tours/{tour}', [TourPagesController::class, 'show'])->name('tours.show');
    Route::inertia('departures', 'Admin/Departures/Index')->name('departures.index');
    Route::inertia('logistics', 'Admin/Logistics/Index')->name('logistics.index');
    Route::get('promotions', [PromotionPagesController::class, 'index'])->name('promotions.index');
    Route::get('newsletter', [NewsletterPagesController::class, 'admin'])->name('newsletter.index');
    Route::get('reviews', [ReviewPagesController::class, 'index'])->name('reviews.index');
    Route::get('team', [TeamPagesController::class, 'index'])->name('team.index');
    Route::inertia('tenant/configuration', 'Admin/Tenant/Configuration')->name('tenant.configuration');
});

Route::middleware(['auth', 'verified', 'tenant_guide.only'])->prefix('guide')->name('guide.')->group(function () {
    Route::get('schedule', [GuidePagesController::class, 'schedule'])->name('schedule');
});

// WHY: marketing/legal pages belong to the platform brand, not to a tenant's
// branded site, so they answer only on the apex host.
Route::domain((string) config('montree.platform_host'))->group(function (): void {
    Route::inertia('faq', 'Faq')->name('faq');
    Route::inertia('politica-de-pago', 'Policies/Payment')->name('policies.payment');
    Route::inertia('politica-de-cancelacion', 'Policies/Cancellation')->name('policies.cancellation');
});

Route::domain((string) config('montree.platform_host'))
    ->middleware(['auth', 'super_admin.only'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');
        Route::get('tenants', [SuperAdminTenantPageController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [SuperAdminTenantPageController::class, 'show'])->name('tenants.show');
    });

require __DIR__.'/settings.php';
