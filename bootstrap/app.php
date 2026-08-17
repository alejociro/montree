<?php

use App\Exceptions\BookingException;
use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\InvalidTourStatusTransitionException;
use App\Exceptions\LogisticsException;
use App\Exceptions\NewsletterException;
use App\Exceptions\PlanLimitReachedException;
use App\Exceptions\PromotionCodeLockedException;
use App\Exceptions\PromotionCodeTakenException;
use App\Exceptions\PromotionInvalidException;
use App\Exceptions\ReviewException;
use App\Exceptions\SubdomainTakenException;
use App\Exceptions\TeamException;
use App\Exceptions\TourDateException;
use App\Exceptions\TourHasActiveBookingsException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantGuide;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectRegistrationToOnboarding;
use App\Http\Middleware\RedirectToPlatformHost;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // WHY: Railway (y otros PaaS) terminan el TLS en un proxy y reenvian
        // HTTP al contenedor. Confiamos en el proxy para que Laravel detecte
        // el esquema HTTPS via X-Forwarded-Proto y genere URLs https. Sin esto,
        // los assets de Vite salen con http:// y el navegador los bloquea por
        // "Mixed Content" (pantalla en blanco).
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            RedirectToPlatformHost::class,
            ResolveTenant::class,
            RedirectRegistrationToOnboarding::class,
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
            'tenant_member.only' => EnsureTenantMember::class,
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
        $exceptions->render(fn (TourDateException $e) => $e->toResponse());
        $exceptions->render(fn (LogisticsException $e) => $e->toResponse());
        $exceptions->render(fn (CrossTenantAccessException $e) => $e->toResponse());

        // WHY: desde F018 un 403 de autorización siempre significa "te falta el permiso X",
        // no "tu rol no es Y". Se normaliza el shape para el frontend (contracts.md §4);
        // las páginas Inertia siguen con el 403 por defecto de Laravel. Se engancha en
        // AccessDeniedHttpException, no en AuthorizationException: el handler ya convirtió
        // la segunda en la primera antes de consultar estos callbacks.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse([
                'message' => __('No tienes permiso para realizar esta acción.'),
                'error_code' => 'INSUFFICIENT_PERMISSION',
            ], 403);
        });
        // WHY: this is the only domain exception reachable from an Inertia form
        // (the signup POST moved off /api/v1). It fires on a race against the
        // tenants.slug unique index, which is semantically the same failure as the
        // `subdomain.unique` rule — so browsers get it back as a field error
        // instead of a 409 that Inertia would surface as an error modal.
        $exceptions->render(function (SubdomainTakenException $e, Request $request) {
            if ($request->expectsJson()) {
                return $e->toResponse();
            }

            // WHY: no withInput() here. Laravel only strips password fields inside
            // its ValidationException path, so flashing input from a custom handler
            // would write the founder's plaintext password to the session store.
            // Inertia's useForm keeps its own state client-side, so it buys nothing.
            return back()->withErrors(['subdomain' => $e->getMessage()]);
        });

        // WHY: onboarding signed links diverge from the default 403 page. An expired
        // verify link shows a branded Inertia page with a resend CTA; an expired
        // claim link bounces to login (its nonce is single-use). Returning null
        // falls back to Laravel's default rendering for every other signed route.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('onboarding.verify')) {
                return Inertia::render('Onboarding/VerificationExpired')
                    ->toResponse($request)
                    ->setStatusCode(403);
            }

            if ($request->routeIs('onboarding.claim')) {
                return redirect()->route('login')->withErrors([
                    'email' => __('El enlace de acceso expiró o ya fue usado. Iniciá sesión de nuevo.'),
                ]);
            }

            return null;
        });
    })->create();
