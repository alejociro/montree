<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Data\Dashboard\DashboardSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DashboardSnapshot $resource
 */
class DashboardResource extends JsonResource
{
    public function __construct(DashboardSnapshot $snapshot, private readonly bool $canExportReports)
    {
        parent::__construct($snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $period = $snapshot->period;

        return [
            'period' => [
                'key' => $period->key,
                'start' => $period->start->toIso8601String(),
                'end' => $period->end->toIso8601String(),
                'previous_start' => $period->previousStart->toIso8601String(),
                'previous_end' => $period->previousEnd->toIso8601String(),
            ],
            'revenue' => [
                'gross' => $snapshot->revenue->gross,
                'net' => $snapshot->revenue->net,
                'currency' => $snapshot->revenue->currency,
                'growth_pct' => $snapshot->revenue->growthPct,
                'previous_gross' => $snapshot->revenue->previousGross,
            ],
            'bookings' => [
                'total' => $snapshot->bookings->total,
                'confirmed' => $snapshot->bookings->confirmed,
                'pending_payment' => $snapshot->bookings->pendingPayment,
                'cancelled' => $snapshot->bookings->cancelled,
                'growth_pct' => $snapshot->bookings->growthPct,
                'previous_total' => $snapshot->bookings->previousTotal,
            ],
            'rating' => [
                'average' => $snapshot->rating->average,
                'count' => $snapshot->rating->count,
            ],
            'occupancy' => [
                'upcoming_dates_count' => $snapshot->occupancy->upcomingDatesCount,
                'total_capacity' => $snapshot->occupancy->totalCapacity,
                'booked_seats' => $snapshot->occupancy->bookedSeats,
                'occupancy_pct' => $snapshot->occupancy->occupancyPct,
            ],
            'top_tours' => $snapshot->topTours->all(),
            'upcoming_dates' => $snapshot->upcomingDates->all(),
            'pending_reviews_count' => $snapshot->pendingReviewsCount,
            'permissions' => [
                'can_export_reports' => $this->canExportReports,
            ],
        ];
    }
}
