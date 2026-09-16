<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\TenantException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Auth\CrossHostLoginHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Abre el panel de una agencia con la sesión del super admin.
 *
 * WHY: no suplanta a un admin del tenant. Emite un handoff para el propio super
 * admin porque las sesiones son host-only (docs/multi-tenancy.md §10) y la del
 * host de plataforma no viaja al subdominio; así el panel queda a su nombre y
 * `tenant_user` no se toca.
 */
final class EnterTenantController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, CrossHostLoginHandoff $handoff): RedirectResponse
    {
        if (! $tenant->canBeEntered()) {
            throw TenantException::notActive();
        }

        $token = $handoff->issue($request->user(), '/admin/dashboard');

        return redirect()->away($this->tenantUrl($request, $tenant)."/auth/handoff/{$token}");
    }

    private function tenantUrl(Request $request, Tenant $tenant): string
    {
        $host = $tenant->slug.'.'.(string) config('montree.platform_host');
        $port = $request->getPort();
        $suffix = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return $request->getScheme()."://{$host}{$suffix}";
    }
}
