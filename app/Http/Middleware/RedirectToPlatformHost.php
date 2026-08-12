<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Multitenancy\SubdomainTenantFinder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collapses the platform's alias hosts onto a single canonical apex.
 *
 * WHY: every reserved host used to render the marketing landing, so the same
 * page answered on `montree.com.co` AND `admin.montree.com.co`, and QA ended up
 * registering agencies on the wrong one. Only reserved hosts sitting *under*
 * the platform apex are redirected, so tenant subdomains (never reserved) and
 * local hosts like `localhost` are untouched.
 */
final class RedirectToPlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower($request->getHost());
        $platformHost = mb_strtolower((string) Config::get('montree.platform_host', ''));

        if (! $this->isPlatformAlias($host, $platformHost)) {
            return $next($request);
        }

        return redirect()->away(
            $request->getScheme().'://'.$platformHost.$request->getRequestUri(),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    /**
     * Brand labels that always belong to the platform, never to a tenant.
     *
     * WHY: checked on top of `reserved_hosts` so the redirect still lands when a
     * deployment forgets to list `admin.<apex>` in MONTREE_RESERVED_HOSTS.
     *
     * @var array<int, string>
     */
    private const ALIAS_LABELS = ['www', 'admin'];

    private function isPlatformAlias(string $host, string $platformHost): bool
    {
        if ($platformHost === '' || $host === $platformHost) {
            return false;
        }

        if (! str_ends_with($host, '.'.$platformHost)) {
            return false;
        }

        if (SubdomainTenantFinder::isReservedHost($host)) {
            return true;
        }

        $label = explode('.', $host)[0];

        return in_array($label, self::ALIAS_LABELS, true);
    }
}
