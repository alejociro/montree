<?php

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTourStatusTransitionException;
use App\Exceptions\NewsletterException;
use App\Exceptions\PlanLimitReachedException;
use App\Exceptions\PromotionCodeLockedException;
use App\Exceptions\PromotionCodeTakenException;
use App\Exceptions\PromotionInvalidException;
use App\Exceptions\ReviewException;
use App\Exceptions\TeamException;
use App\Exceptions\TourHasActiveBookingsException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantGuide;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            ResolveTenant::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // WHY: this app is a same-origin Inertia SPA. The /api/v1/* endpoints
        // need session cookies (auth via guard 'web') just like the Inertia
        // pages. Laravel 11+'s api group is stateless by default, so we make
        // it stateful here. External webhooks (Stripe etc.) can be placed in
        // a dedicated route file later if they need to stay stateless.
        $middleware->api(prepend: [
            ResolveTenant::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'super_admin.only' => EnsureSuperAdmin::class,
            'tenant_admin.only' => EnsureTenantAdmin::class,
            'tenant_guide.only' => EnsureTenantGuide::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (PlanLimitReachedException $e) => $e->toResponse());
        $exceptions->render(fn (InvalidTourStatusTransitionException $e) => $e->toResponse());
        $exceptions->render(fn (TourHasActiveBookingsException $e) => $e->toResponse());
        $exceptions->render(fn (PromotionCodeTakenException $e) => $e->toResponse());
        $exceptions->render(fn (PromotionCodeLockedException $e) => $e->toResponse());
        $exceptions->render(fn (PromotionInvalidException $e) => $e->toResponse());
        $exceptions->render(fn (BookingException $e) => $e->toResponse());
        $exceptions->render(fn (ReviewException $e) => $e->toResponse());
        $exceptions->render(fn (NewsletterException $e) => $e->toResponse());
        $exceptions->render(fn (TeamException $e) => $e->toResponse());
    })->create();
