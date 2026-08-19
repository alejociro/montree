<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class SeedDefaultCategoriesAction
{
    /**
     * Seed the configured starter categories into a freshly provisioned tenant
     * so its tour form never opens with an empty category select.
     *
     * @return int number of categories created
     */
    public function handle(Tenant $tenant): int
    {
        $created = 0;

        foreach ($this->defaults() as $index => $payload) {
            $slug = Str::slug($payload['name']);

            $exists = Category::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->getKey())
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                continue;
            }

            Category::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => $payload['name'],
                'slug' => $slug,
                'icon' => $payload['icon'] ?? null,
                'display_order' => $index,
                'is_active' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @return array<int, array{name:string, icon?:string|null}>
     */
    private function defaults(): array
    {
        $configured = Config::get('montree.default_categories', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            $configured,
            static fn (mixed $row): bool => is_array($row) && isset($row['name']) && is_string($row['name']),
        ));
    }
}
