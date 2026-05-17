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
        return Cache::remember(
            self::key($slug),
            self::TTL_SECONDS,
            fn (): ?Tenant => Tenant::query()->with('configuration')->where('slug', $slug)->first(),
        );
    }

    public function forget(string $slug): void
    {
        Cache::forget(self::key($slug));
    }
}
