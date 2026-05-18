<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\OccupancyBreakdown;
use App\Enums\TourDateStatus;
use App\Models\TourDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OccupancyCalculator
{
    public function upcoming(int $days = 7): OccupancyBreakdown
    {
        $now = Carbon::now();
        $end = $now->copy()->addDays($days);

        $aggregates = TourDate::query()
            ->whereBetween('starts_at', [$now, $end])
            ->whereIn('status', [TourDateStatus::Open->value, TourDateStatus::Full->value])
            ->selectRaw('COUNT(*) as dates_count, COALESCE(SUM(capacity), 0) as total_capacity, COALESCE(SUM(booked_count), 0) as total_booked')
            ->first();

        $datesCount = (int) ($aggregates->dates_count ?? 0);
        $totalCapacity = (int) ($aggregates->total_capacity ?? 0);
        $totalBooked = (int) ($aggregates->total_booked ?? 0);

        return new OccupancyBreakdown(
            upcomingDatesCount: $datesCount,
            totalCapacity: $totalCapacity,
            bookedSeats: $totalBooked,
            occupancyPct: $this->occupancyPct($totalCapacity, $totalBooked),
        );
    }

    public function upcomingDatesList(int $days = 7, int $limit = 10): Collection
    {
        $now = Carbon::now();
        $end = $now->copy()->addDays($days);

        return TourDate::query()
            ->whereBetween('starts_at', [$now, $end])
            ->whereIn('status', [TourDateStatus::Open->value, TourDateStatus::Full->value])
            ->with(['tour:id,name,slug', 'guide:id,name'])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (TourDate $date): array => [
                'id' => $date->id,
                'tour_id' => $date->tour_id,
                'tour_name' => $date->tour?->name,
                'starts_at' => $date->starts_at->toIso8601String(),
                'capacity_total' => $date->capacity,
                'capacity_booked' => $date->booked_count,
                'occupancy_pct' => $this->occupancyPct($date->capacity, $date->booked_count),
                'guide_name' => $date->guide?->name,
            ]);
    }

    private function occupancyPct(int $capacity, int $booked): ?float
    {
        if ($capacity === 0) {
            return null;
        }

        return round(($booked / $capacity) * 100, 1);
    }
}
