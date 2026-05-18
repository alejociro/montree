<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardSnapshot;
use App\Data\Dashboard\RatingSummary;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

final class DashboardMetricsAggregator
{
    public function __construct(
        private RevenueCalculator $revenueCalculator,
        private BookingCounters $bookingCounters,
        private TopToursResolver $topToursResolver,
        private OccupancyCalculator $occupancyCalculator,
    ) {}

    public function for(Tenant $tenant, PeriodFilter $period): DashboardSnapshot
    {
        $revenue = $this->revenueCalculator->between(
            $tenant,
            $period->start,
            $period->end,
            $period->previousStart,
            $period->previousEnd,
        );

        $bookings = $this->bookingCounters->between(
            $period->start,
            $period->end,
            $period->previousStart,
            $period->previousEnd,
        );

        $rating = $this->resolveRating($period->start, $period->end);
        $occupancy = $this->occupancyCalculator->upcoming();
        $topTours = $this->topToursResolver->for($period->start, $period->end);
        $upcomingDates = $this->occupancyCalculator->upcomingDatesList();

        $pendingReviewsCount = Review::query()
            ->where('status', ReviewStatus::Pending->value)
            ->count();

        return new DashboardSnapshot(
            period: $period,
            revenue: $revenue,
            bookings: $bookings,
            rating: $rating,
            occupancy: $occupancy,
            topTours: $topTours,
            upcomingDates: $upcomingDates,
            pendingReviewsCount: $pendingReviewsCount,
        );
    }

    private function resolveRating(Carbon $start, Carbon $end): RatingSummary
    {
        $aggregates = Review::query()
            ->where('status', ReviewStatus::Approved->value)
            ->whereBetween('approved_at', [$start, $end])
            ->selectRaw('COALESCE(AVG(rating), 0) as average, COUNT(*) as total')
            ->first();

        $average = number_format((float) ($aggregates->average ?? 0), 2, '.', '');
        $count = (int) ($aggregates->total ?? 0);

        return new RatingSummary($average, $count);
    }
}
