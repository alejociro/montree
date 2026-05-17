<?php

declare(strict_types=1);

namespace App\Multitenancy;

use App\Models\Tenant;
use App\Services\Tenant\TenantConfigurationCache;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

final class SubdomainTenantFinder extends TenantFinder
{
    /**
     * Hosts that NEVER resolve to a tenant (platform landing).
     *
     * Single source of truth for reserved hosts. Consumed here and by
     * App\Http\Middleware\ResolveTenant via the static helper.
     *
     * @var array<int, string>
     */
    private const RESERVED_HOSTS = [
        'montree.app',
        'www.montree.app',
        'montree.test',
        'www.montree.test',
        'admin.montree.app',
        'admin.montree.test',
        'api.montree.app',
        'api.montree.test',
        'localhost',
        '127.0.0.1',
    ];

    public function __construct(private TenantConfigurationCache $cache) {}

    public static function isReservedHost(string $host): bool
    {
        return in_array(strtolower($host), self::RESERVED_HOSTS, true);
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
