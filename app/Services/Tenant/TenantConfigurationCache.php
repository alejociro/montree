<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

final class TenantConfigurationCache
{
    private const TTL_SECONDS = 300;

    public static function key(string $slug): string
    {
        return "tenant:{$slug}";
    }

    public function forSlug(string $slug): ?Tenant
    {
        $cached = Cache::get(self::key($slug));

        // WHY: a stale cache entry (e.g. after migrate:fresh changed model schema)
        // can deserialize as __PHP_Incomplete_Class. Treat any non-Tenant payload
        // as a miss and refresh.
        if ($cached !== null && ! $cached instanceof Tenant) {
            Cache::forget(self::key($slug));
            $cached = null;
        }

        if ($cached instanceof Tenant) {
            return $cached;
        }

        $tenant = Tenant::query()->with('configuration')->where('slug', $slug)->first();
        if ($tenant !== null) {
            Cache::put(self::key($slug), $tenant, self::TTL_SECONDS);
        }

        return $tenant;
    }

    public function forget(string $slug): void
    {
        Cache::forget(self::key($slug));
    }
}
