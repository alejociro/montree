<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Services\Dashboard\PeriodFilter;
use Illuminate\Support\Collection;

final readonly class DashboardSnapshot
{
    /**
     * @param  Collection<int, array<string, mixed>>  $topTours
     * @param  Collection<int, array<string, mixed>>  $upcomingDates
     */
    public function __construct(
        public PeriodFilter $period,
        public RevenueBreakdown $revenue,
        public BookingCounts $bookings,
        public RatingSummary $rating,
        public OccupancyBreakdown $occupancy,
        public Collection $topTours,
        public Collection $upcomingDates,
        public int $pendingReviewsCount,
    ) {}
}
