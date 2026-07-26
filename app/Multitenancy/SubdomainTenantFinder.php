<?php

declare(strict_types=1);

namespace App\Multitenancy;

use App\Models\Tenant;
use App\Services\Tenant\TenantConfigurationCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

final class SubdomainTenantFinder extends TenantFinder
{
    public function __construct(private TenantConfigurationCache $cache) {}

    /**
     * Hosts that NEVER resolve to a tenant (platform landing).
     *
     * Driven by `montree.reserved_hosts` so the same build can run on any
     * domain. Consumed here and by App\Http\Middleware\ResolveTenant.
     */
    public static function isReservedHost(string $host): bool
    {
        return in_array(strtolower($host), self::reservedHosts(), true);
    }

    /**
     * @return array<int, string>
     */
    private static function reservedHosts(): array
    {
        $configured = explode(',', (string) Config::get('montree.reserved_hosts', ''));

        return array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            $configured,
        )));
    }

    public function findForRequest(Request $request): ?IsTenant
    {
        $host = strtolower($request->getHost());

        if (self::isReservedHost($host)) {
            return null;
        }

        $slug = $this->extractSlug($host);

        if ($slug === null) {
            return null;
        }

        return $this->cache->forSlug($slug);
    }

    private function extractSlug(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 2) {
            return null;
        }

        $slug = $parts[0];

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug)) {
            return null;
        }

        return $slug;
    }
}
