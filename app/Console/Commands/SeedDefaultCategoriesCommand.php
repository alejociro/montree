<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenant\SeedDefaultCategoriesAction;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class SeedDefaultCategoriesCommand extends Command
{
    protected $signature = 'montree:seed-default-categories {--tenant= : Tenant slug; omit to backfill every tenant}';

    protected $description = 'Backfill the default tour categories into existing tenants';

    public function handle(SeedDefaultCategoriesAction $seedCategories): int
    {
        $slug = $this->option('tenant');

        $tenants = Tenant::query()
            ->when(is_string($slug), fn ($query) => $query->where('slug', $slug))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->components->error(is_string($slug) ? "No tenant found with slug [$slug]." : 'No tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $created = $seedCategories->handle($tenant);

            $this->components->twoColumnDetail(
                $tenant->slug,
                $created === 0 ? '<fg=gray>sin cambios</>' : "<fg=green>+$created</>",
            );
        }

        return self::SUCCESS;
    }
}
