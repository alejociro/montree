<?php

declare(strict_types=1);

namespace App\Multitenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

final class SubdomainTenantFinder extends TenantFinder
{
    /**
     * Hosts that NEVER resolve to a tenant (platform landing).
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
        'localhost',
        '127.0.0.1',
    ];

    public function findForRequest(Request $request): ?IsTenant
    {
        $host = strtolower($request->getHost());

        if (in_array($host, self::RESERVED_HOSTS, true)) {
            return null;
        }

        $slug = $this->extractSlug($host);

        if ($slug === null) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
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
