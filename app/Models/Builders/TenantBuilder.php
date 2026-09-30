<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Data\SuperAdmin\TenantFilters;
use App\Models\Tenant;
use App\Support\MonthlySeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Tenant>
 */
final class TenantBuilder extends Builder
{
    public function applyFilters(TenantFilters $filters): self
    {
        return $this
            ->when($filters->search !== null, fn (self $query) => $query->matching($filters->search))
            ->when($filters->status !== null, fn (self $query) => $query->where('status', $filters->status))
            ->orderBy($filters->sort, $filters->direction);
    }

    public function matching(?string $search): self
    {
        if ($search === null || $search === '') {
            return $this;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->where(fn (self $query) => $query
            ->where('name', 'like', $term)
            ->orWhere('slug', 'like', $term));
    }

    /**
     * @return array<string, int>
     */
    public function registeredPerMonth(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = $this->clone()
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at'])
            ->map(fn (Tenant $tenant) => $tenant->created_at);

        return MonthlySeries::count($dates);
    }
}
