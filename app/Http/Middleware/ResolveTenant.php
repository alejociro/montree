<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Http\Controllers\Errors\TenantNotFoundController;
use App\Http\Controllers\Errors\TenantSuspendedController;
use App\Models\Tenant;
use App\Multitenancy\SubdomainTenantFinder;
use Closure;
use Illuminate\Http\Request;
use Spatie\Multitenancy\TenantFinder\TenantFinder;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(
        private TenantFinder $finder,
        private TenantNotFoundController $notFound,
        private TenantSuspendedController $suspended,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->finder->findForRequest($request);

        if ($resolved instanceof Tenant) {
            $resolved->makeCurrent();
        } else {
            Tenant::forgetCurrent();
        }

        $tenant = Tenant::current();

        if ($tenant === null) {
            if (SubdomainTenantFinder::isReservedHost($request->getHost())) {
                return $next($request);
            }

            return ($this->notFound)($request);
        }

        if ($tenant->status === TenantStatus::Suspended) {
            return ($this->suspended)($request, $tenant);
        }

        // WHY: spatie/permission with teams=true scopes role checks by team_id.
        // Set BEFORE downstream middleware (auth, role checks) so policies see the
        // correct tenant context.
        setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}
