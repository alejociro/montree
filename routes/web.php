<?php

use App\Http\Controllers\AccountPagesController;
use App\Http\Controllers\Admin\AssignGuideController;
use App\Http\Controllers\Admin\CancelTourDateController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryPagesController;
use App\Http\Controllers\Admin\DefaultRouteController;
use App\Http\Controllers\Admin\DepartureFormPagesController;
use App\Http\Controllers\Admin\DeparturePagesController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\LogisticsPagesController;
use App\Http\Controllers\Admin\PromotionPagesController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ReorderCategoriesController;
use App\Http\Controllers\Admin\RestoreTourDateController;
use App\Http\Controllers\Admin\ReviewPagesController;
use App\Http\Controllers\Admin\TeamPagesController;
use App\Http\Controllers\Admin\TenantConfigurationPagesController;
use App\Http\Controllers\Admin\TourDatePagesController;
use App\Http\Controllers\Admin\TourImageController;
use App\Http\Controllers\Admin\TourPagesController;
use App\Http\Controllers\Admin\TourRouteController;
use App\Http\Controllers\Admin\TourStatusController;
use App\Http\Controllers\Auth\CrossHostLoginController;
use App\Http\Controllers\BookingPagesController;
use App\Http\Controllers\CatalogPagesController;
use App\Http\Controllers\DashboardPagesController;
use App\Http\Controllers\Guide\GuidePagesController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\NewsletterPagesController;
use App\Http\Controllers\NotificationPagesController;
use App\Http\Controllers\Onboarding\AgencyOnboardingController;
use App\Http\Controllers\Onboarding\ClaimAgencyController;
use App\Http\Controllers\Onboarding\SubdomainAvailabilityController;
use App\Http\Controllers\PaymentCheckoutController;
use App\Http\Controllers\PaymentNotificationController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PolicyPagesController;
use App\Http\Controllers\PublicTourPageController;
use App\Http\Controllers\QueryTransactionController;
use App\Http\Controllers\RoleHomeRedirectController;
use App\Http\Controllers\SuperAdmin\CommissionSchedulePageController;
use App\Http\Controllers\SuperAdmin\EnterTenantController;
use App\Http\Controllers\SuperAdmin\PlatformChargePageController;
use App\Http\Controllers\SuperAdmin\StoreTenantController;
use App\Http\Controllers\SuperAdmin\StoreTenantUserController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\SuperAdminTenantPageController;
use App\Http\Controllers\SuperAdmin\UpdateGlobalCommissionScheduleController;
use App\Http\Controllers\SuperAdmin\UpdateTenantCommissionController;
use App\Http\Controllers\SuperAdmin\UpdateTenantConfigurationController;
use App\Http\Controllers\SuperAdmin\UpdateTenantStatusController;
use App\Http\Controllers\TransactionPagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePageController::class)->name('home');

Route::get('tours', [CatalogPagesController::class, 'index'])->name('catalog.index');
Route::get('tours/{slug}', [PublicTourPageController::class, 'show'])->name('tours.show');
Route::get('unsubscribe/{token}', [NewsletterPagesController::class, 'unsubscribe'])->middleware('module:newsletter')->name('newsletter.unsubscribe.page');

// WHY: cross-host login handoff (isolated per-subdomain sessions, see §10). Public
// by design — the single-use token IS the credential. Logs the user in on this host.
Route::get('auth/handoff/{token}', CrossHostLoginController::class)
    ->middleware('throttle:10,1')
    ->name('auth.handoff');

Route::get('booking/new', [BookingPagesController::class, 'create'])->name('booking.new');

// WHY: los términos son de la agencia y se leen desde el checkout, en el
// subdominio del tenant. Por eso la ruta va fuera del grupo de platform_host.
Route::get('terminos', [PolicyPagesController::class, 'terms'])->name('policies.terms');

Route::match(['get', 'post'], 'payments/{payment}/return', PaymentReturnController::class)
    ->middleware('signed')
    ->name('payments.return');

Route::post('payments/notification', PaymentNotificationController::class)->name('payments.notification');

// WHY: self-serve onboarding (F016). `/start` + check-email + resend + verify run
// on the platform host; `claim` runs on the tenant subdomain and produces the
// founder's host-scoped session. verify/claim are gated by signed URLs (the
// signature is the credential); onboarding.claim additionally consumes a one-shot
// nonce. These are web routes on purpose: this is a same-origin Inertia monolith,
// so a JSON layer between the form and the action would buy nothing.
Route::get('start', [AgencyOnboardingController::class, 'create'])->name('onboarding.start');
Route::post('onboarding/agencies', [AgencyOnboardingController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('onboarding.agencies.store');
Route::get('onboarding/check-email', [AgencyOnboardingController::class, 'checkEmail'])->name('onboarding.check-email');
Route::post('onboarding/resend-verification', [AgencyOnboardingController::class, 'resendVerification'])
    ->middleware('throttle:3,10')
    ->name('onboarding.resend-verification');
Route::get('onboarding/verify/{tenant}/{user}', [AgencyOnboardingController::class, 'verify'])
    ->middleware('signed')
    ->name('onboarding.verify');
Route::get('onboarding/subdomain-availability', SubdomainAvailabilityController::class)
    ->middleware('throttle:30,1')
    ->name('onboarding.subdomain-availability');
Route::get('onboarding/claim', ClaimAgencyController::class)
    ->middleware('signed')
    ->name('onboarding.claim');

Route::middleware(['auth', 'verified', 'tenant_member.only'])->group(function () {
    Route::get('dashboard', RoleHomeRedirectController::class)->name('dashboard');
    Route::get('bookings/{bookingNumber}', [BookingPagesController::class, 'show'])->name('booking.show');
    Route::post('bookings/{bookingNumber}/pay', [PaymentCheckoutController::class, 'store'])->name('booking.pay');

    // WHY: F018 B4 — la zona de viajero es solo del cliente. Quien tiene permisos de
    // panel (`dashboard.view` o `guide.schedule.view`) vuelve a su home de rol.
    Route::middleware('traveler.only')->group(function () {
        Route::get('account', [AccountPagesController::class, 'profile'])->name('account.profile');
        Route::get('account/bookings', [AccountPagesController::class, 'bookings'])->name('account.bookings');
        Route::get('account/favorites', [AccountPagesController::class, 'favorites'])->name('account.favorites');
        Route::get('account/notifications', [NotificationPagesController::class, 'index'])->name('account.notifications');
        Route::get('account/bookings/{bookingNumber}/review', [AccountPagesController::class, 'review'])->name('account.bookings.review');
    });
});

// WHY: mismo criterio que routes/api.php — `dashboard.view` abre el panel y cada pantalla
// exige además el permiso de su módulo (F018 contracts.md §1).
Route::middleware(['auth', 'verified', 'tenant_admin.only', 'can:dashboard.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', DashboardPagesController::class)->name('dashboard');
    Route::get('tours', [TourPagesController::class, 'index'])->middleware('can:tours.view')->name('tours.index');
    Route::get('tours/create', [TourPagesController::class, 'create'])->middleware('can:tours.create')->name('tours.create');
    Route::post('tours', [TourPagesController::class, 'store'])->middleware('can:tours.create')->name('tours.store');
    Route::get('tours/{tour}/edit', [TourPagesController::class, 'edit'])->middleware('can:tours.update')->name('tours.edit');
    Route::get('tours/{tour}', [TourPagesController::class, 'show'])->middleware('can:tours.view')->name('tours.show');
    Route::put('tours/{tour}', [TourPagesController::class, 'update'])->middleware('can:tours.update')->name('tours.update');
    Route::delete('tours/{tour}', [TourPagesController::class, 'destroy'])->middleware('can:tours.delete')->name('tours.destroy');
    Route::patch('tours/{tour}/status', TourStatusController::class)->middleware('can:tours.publish')->name('tours.status');

    Route::middleware('can:tours.images.manage')->group(function (): void {
        Route::post('tours/{tour}/images', [TourImageController::class, 'store'])->name('tours.images.store');
        Route::patch('tours/{tour}/images/{image}', [TourImageController::class, 'update'])->name('tours.images.update');
        Route::delete('tours/{tour}/images/{image}', [TourImageController::class, 'destroy'])->name('tours.images.destroy');
    });

    Route::get('departures', [DeparturePagesController::class, 'index'])->middleware('can:departures.view')->name('departures.index');
    // T9: vista paso a paso de crear/editar salida (reemplaza TourDateFormDialog).
    Route::get('departures/create', [DepartureFormPagesController::class, 'create'])->middleware('can:departures.create')->name('departures.create');
    Route::get('departures/{tourDate}/edit', [DepartureFormPagesController::class, 'edit'])->middleware('can:departures.update')->name('departures.edit');
    Route::get('tours/{tour}/departures/create', [DepartureFormPagesController::class, 'createForTour'])->middleware('can:departures.create')->name('tours.departures.create');
    Route::post('tours/{tour}/dates', [TourDatePagesController::class, 'store'])->middleware('can:departures.create')->name('tours.dates.store');
    Route::put('tour-dates/{tourDate}', [TourDatePagesController::class, 'update'])->middleware('can:departures.update')->name('tour-dates.update');
    Route::delete('tour-dates/{tourDate}', [TourDatePagesController::class, 'destroy'])->middleware('can:departures.delete')->name('tour-dates.destroy');
    Route::patch('tour-dates/{tourDate}/cancel', CancelTourDateController::class)->middleware('can:departures.cancel')->name('tour-dates.cancel');
    Route::patch('tour-dates/{tourDate}/restore', RestoreTourDateController::class)->middleware('can:departures.cancel')->name('tour-dates.restore');
    Route::patch('tour-dates/{tourDate}/guide', AssignGuideController::class)->middleware('can:departures.assign_guide')->name('tour-dates.guide');

    Route::get('categories', [CategoryPagesController::class, 'index'])->middleware('can:categories.view')->name('categories.index');

    Route::middleware('can:categories.manage')->group(function (): void {
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('categories/reorder', ReorderCategoriesController::class)->name('categories.reorder');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    Route::get('logistics', [LogisticsPagesController::class, 'index'])->middleware(['module:logistics', 'can:logistics.view'])->name('logistics.index');

    Route::middleware('can:tours.update')->group(function (): void {
        Route::post('tours/{tour}/routes', [TourRouteController::class, 'store'])->name('tours.routes.store');
        Route::put('routes/{route}', [TourRouteController::class, 'update'])->name('routes.update');
        Route::delete('routes/{route}', [TourRouteController::class, 'destroy'])->name('routes.destroy');
        Route::patch('routes/{route}/default', DefaultRouteController::class)->name('routes.default');
    });

    Route::middleware(['module:logistics', 'can:logistics.manage'])->group(function (): void {
        Route::post('providers', [ProviderController::class, 'store'])->name('providers.store');
        Route::put('providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
        Route::delete('providers/{provider}', [ProviderController::class, 'destroy'])->name('providers.destroy');
        Route::post('hotels', [HotelController::class, 'store'])->name('hotels.store');
        Route::put('hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update');
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy'])->name('hotels.destroy');
    });
    Route::get('promotions', [PromotionPagesController::class, 'index'])->middleware(['module:promotions', 'can:promotions.view'])->name('promotions.index');
    Route::get('newsletter', [NewsletterPagesController::class, 'admin'])->middleware(['module:newsletter', 'can:newsletter.view'])->name('newsletter.index');
    Route::get('reviews', [ReviewPagesController::class, 'index'])->middleware('can:reviews.view')->name('reviews.index');
    Route::get('team', [TeamPagesController::class, 'index'])->middleware('can:team.view')->name('team.index');
    Route::inertia('roles', 'Admin/Roles/Index')->middleware('can:team.role.update')->name('roles.index');
    Route::get('transactions', [TransactionPagesController::class, 'index'])->middleware('can:payments.view')->name('transactions.index');
    Route::get('transactions/{payment}', [TransactionPagesController::class, 'show'])->middleware('can:payments.view')->name('transactions.show');
    Route::post('transactions/{payment}/query', QueryTransactionController::class)->middleware('can:payments.query')->name('transactions.query');
    Route::get('tenant/configuration', [TenantConfigurationPagesController::class, 'index'])->middleware('can:tenant.view')->name('tenant.configuration');
    Route::post('tenant/configuration', [TenantConfigurationPagesController::class, 'update'])->middleware('can:tenant.settings.update')->name('tenant.configuration.update');
});

Route::middleware(['auth', 'verified', 'tenant_guide.only'])->prefix('guide')->name('guide.')->group(function () {
    Route::get('schedule', [GuidePagesController::class, 'schedule'])->middleware('can:guide.schedule.view')->name('schedule');
    Route::get('tour-dates/{tourDate}/passengers', [GuidePagesController::class, 'passengers'])->middleware('can:guide.travelers.view')->name('passengers');
    // WHY: sin `can:` — el detalle del tour se alcanza por pertenencia (tener
    // al menos una salida asignada), no por permiso de catálogo (D1).
    Route::get('tours/{tour}', [GuidePagesController::class, 'tour'])->name('tours.show');
});

// WHY: marketing/legal pages belong to the platform brand, not to a tenant's
// branded site, so they answer only on the apex host.
Route::domain((string) config('montree.platform_host'))->group(function (): void {
    Route::inertia('faq', 'Faq')->name('faq');
    Route::inertia('politica-de-pago', 'Policies/Payment')->name('policies.payment');
    // WHY: la cancelación de un tour es del contrato agencia–viajero y vive en los
    // términos de cada agencia (/terminos en su subdominio). La URL vieja se
    // conserva como redirección para no romper enlaces publicados.
    Route::permanentRedirect('politica-de-cancelacion', '/terminos-y-condiciones');
    Route::inertia('terminos-y-condiciones', 'Policies/PlatformTerms')->name('policies.platform-terms');
    Route::inertia('politica-de-privacidad', 'Policies/Privacy')->name('policies.privacy');
    Route::inertia('politica-de-cookies', 'Policies/Cookies', [
        'session' => [
            'cookie' => config('session.cookie'),
            'lifetimeMinutes' => (int) config('session.lifetime'),
        ],
    ])->name('policies.cookies');
});

Route::domain((string) config('montree.platform_host'))
    ->middleware(['auth', 'super_admin.only'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');
        Route::get('tenants', [SuperAdminTenantPageController::class, 'index'])->name('tenants.index');
        Route::post('tenants', StoreTenantController::class)->name('tenants.store');
        Route::get('tenants/{tenant}', [SuperAdminTenantPageController::class, 'show'])->name('tenants.show');
        Route::post('tenants/{tenant}/enter', EnterTenantController::class)->name('tenants.enter');
        Route::post('tenants/{tenant}/users', StoreTenantUserController::class)->name('tenants.users.store');
        Route::patch('tenants/{tenant}/status', UpdateTenantStatusController::class)->name('tenants.status.update');
        Route::post('tenants/{tenant}/configuration', UpdateTenantConfigurationController::class)->name('tenants.configuration.update');
        Route::put('tenants/{tenant}/commission', UpdateTenantCommissionController::class)->name('tenants.commission.update');
        Route::get('tenants/{tenant}/charges', [PlatformChargePageController::class, 'index'])->name('tenants.charges.index');

        Route::get('commission', [CommissionSchedulePageController::class, 'index'])->name('commission.edit');
        Route::put('commission', UpdateGlobalCommissionScheduleController::class)->name('commission.update');
    });

require __DIR__.'/settings.php';
