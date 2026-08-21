<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\GuideAvailability;
use App\Data\GuideBusyBlock;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Los días que cada guía ya tiene ocupados (D9). La comparten el select del
 * panel —para no ofrecer lo que se va a rechazar— y la regla
 * `App\Rules\GuideIsAvailable`, que corren los tres caminos que asignan guía.
 *
 * WHY: la ocupación se mide por días calendario `[date(starts_at) …
 * date(ends_at)]`, así que todos los cortes van por `DATE(...)` y no por el
 * instante. `COALESCE(ends_at, starts_at)` cubre el dato viejo al que nadie le
 * derivó el fin.
 */
final class GuideAvailabilityQuery
{
    /**
     * Los guías activos del tenant con sus bloques ocupados en el rango.
     *
     * @return Collection<int, GuideAvailability>
     */
    public function handle(CarbonInterface $from, CarbonInterface $to, ?int $excludeTourDateId = null): Collection
    {
        $guides = $this->tenantGuides();

        if ($guides->isEmpty()) {
            return collect();
        }

        $blocks = $this->blocks($guides->keys()->all(), $from, $to, $excludeTourDateId);

        return $guides
            ->map(fn (string $name, int $id): GuideAvailability => new GuideAvailability(
                $id,
                $name,
                $blocks->get($id, collect())->all(),
            ))
            ->values();
    }

    /**
     * Los bloques que le impiden a un guía tomar el rango pedido. Vacío = libre.
     *
     * @return array<int, GuideBusyBlock>
     */
    public function busyFor(int $guideId, CarbonInterface $from, CarbonInterface $to, ?int $excludeTourDateId = null): array
    {
        return $this->blocks([$guideId], $from, $to, $excludeTourDateId)
            ->get($guideId, collect())
            ->all();
    }

    /**
     * Las salidas que quedarían en solape si el tour cambiara su duración.
     * Cambiar `duration_hours` alarga retroactivamente el `ends_at` derivado de
     * todas sus salidas futuras y puede cruzar dos que hoy no se tocan.
     *
     * @return array<int, string> el motivo de cada choque, ya legible
     */
    public function durationChangeConflicts(Tour $tour, int $durationHours): array
    {
        $today = Carbon::today();

        /** @var Collection<int, TourDate> $departures */
        $departures = TourDate::query()
            ->occupying()
            ->whereRaw('DATE(COALESCE(ends_at, starts_at)) >= ?', [$today->toDateString()])
            ->with(['tour:id,name', 'guide:id,name'])
            ->orderBy('starts_at')
            ->get();

        $projected = $departures
            ->map(function (TourDate $departure) use ($tour, $durationHours): array {
                $end = $departure->tour_id === $tour->getKey()
                    ? TourDate::deriveEndsAt($departure->starts_at, $durationHours)
                    : ($departure->ends_at ?? $departure->starts_at);

                return [
                    'departure' => $departure,
                    'from' => $departure->starts_at->copy()->startOfDay(),
                    'to' => $end->copy()->startOfDay(),
                ];
            })
            ->groupBy(fn (array $entry): int => (int) $entry['departure']->guide_id);

        $conflicts = [];

        foreach ($projected as $ofGuide) {
            $entries = $ofGuide->values()->all();

            foreach ($entries as $index => $one) {
                foreach (array_slice($entries, $index + 1) as $other) {
                    if ($one['from']->lte($other['to']) && $other['from']->lte($one['to'])) {
                        $conflicts[] = $this->conflictLabel($one, $other);
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<int, int>  $guideIds
     * @return Collection<int, Collection<int, GuideBusyBlock>> bloques por `guide_id`
     */
    private function blocks(array $guideIds, CarbonInterface $from, CarbonInterface $to, ?int $excludeTourDateId): Collection
    {
        if ($guideIds === []) {
            return collect();
        }

        return TourDate::query()
            ->occupying()
            ->whereIn('guide_id', $guideIds)
            ->when($excludeTourDateId !== null, fn (Builder $query) => $query->whereKeyNot($excludeTourDateId))
            ->whereRaw('DATE(starts_at) <= ?', [$to->toDateString()])
            ->whereRaw('DATE(COALESCE(ends_at, starts_at)) >= ?', [$from->toDateString()])
            ->with('tour:id,name')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('guide_id')
            ->map(fn (Collection $departures): Collection => $departures->map(
                fn (TourDate $departure): GuideBusyBlock => $this->block($departure),
            )->values());
    }

    private function block(TourDate $departure): GuideBusyBlock
    {
        return new GuideBusyBlock(
            (int) $departure->getKey(),
            $departure->tour?->name ?? '',
            $departure->starts_at->toDateString(),
            ($departure->ends_at ?? $departure->starts_at)->toDateString(),
            $departure->status,
        );
    }

    /**
     * Los guías activos del tenant, `id => name`.
     *
     * @return Collection<int, string>
     */
    private function tenantGuides(): Collection
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return collect();
        }

        setPermissionsTeamId($tenant->getKey());

        return $tenant->users()
            ->wherePivot('status', TenantMembershipStatus::Active->value)
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Guide->value))
            ->orderBy('users.name')
            ->pluck('users.name', 'users.id');
    }

    /**
     * @param  array{departure:TourDate, from:CarbonInterface, to:CarbonInterface}  $one
     * @param  array{departure:TourDate, from:CarbonInterface, to:CarbonInterface}  $other
     */
    private function conflictLabel(array $one, array $other): string
    {
        return __(':first se cruzaría con :second (guía :guide).', [
            'first' => $this->projectedLabel($one),
            'second' => $this->projectedLabel($other),
            'guide' => $one['departure']->guide?->name ?? (string) $one['departure']->guide_id,
        ]);
    }

    /**
     * @param  array{departure:TourDate, from:CarbonInterface, to:CarbonInterface}  $entry
     */
    private function projectedLabel(array $entry): string
    {
        return (new GuideBusyBlock(
            (int) $entry['departure']->getKey(),
            $entry['departure']->tour?->name ?? '',
            $entry['from']->toDateString(),
            $entry['to']->toDateString(),
            $entry['departure']->status,
        ))->label();
    }
}
