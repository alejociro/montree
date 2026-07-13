<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TourDateStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

final class BuildTourShowStatsAction
{
    /**
     * @return array{
     *     bookings: array{total: int, confirmed: int, pending_payment: int, cancelled: int},
     *     travelers_total: int,
     *     revenue_total: string,
     *     currency: string,
     *     occupancy_upcoming: array{booked_total: int, capacity_total: int, rate: int},
     *     upcoming_dates_count: int,
     *     next_date_starts_at: string|null,
     * }
     */
    public function handle(Tour $tour): array
    {
        $bookingCounts = Booking::query()
            ->where('tour_id', $tour->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $revenue = Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', PaymentStatus::Completed->value)
            ->where('bookings.tour_id', $tour->id)
            ->sum('payments.amount');

        $travelersTotal = Booking::query()
            ->where('tour_id', $tour->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->sum('travelers_count');

        $occupancy = TourDate::query()
            ->where('tour_id', $tour->id)
            ->where('starts_at', '>', now())
            ->whereIn('status', [TourDateStatus::Open->value, TourDateStatus::Full->value])
            ->selectRaw('COALESCE(SUM(booked_count), 0) as booked_total')
            ->selectRaw('COALESCE(SUM(capacity), 0) as capacity_total')
            ->selectRaw('COUNT(*) as dates_count')
            ->selectRaw('MIN(starts_at) as next_starts_at')
            ->first();

        $bookedTotal = (int) ($occupancy?->getAttribute('booked_total') ?? 0);
        $capacityTotal = (int) ($occupancy?->getAttribute('capacity_total') ?? 0);
        $nextStartsAt = $occupancy?->getAttribute('next_starts_at');

        return [
            'bookings' => [
                'total' => (int) $bookingCounts->sum(),
                'confirmed' => (int) $bookingCounts->get(BookingStatus::Confirmed->value, 0),
                'pending_payment' => (int) $bookingCounts->get(BookingStatus::PendingPayment->value, 0),
                'cancelled' => (int) $bookingCounts->get(BookingStatus::Cancelled->value, 0),
            ],
            'travelers_total' => (int) $travelersTotal,
            'revenue_total' => number_format((float) $revenue, 2, '.', ''),
            'currency' => $tour->currency,
            'occupancy_upcoming' => [
                'booked_total' => $bookedTotal,
                'capacity_total' => $capacityTotal,
                'rate' => $capacityTotal > 0 ? (int) round($bookedTotal / $capacityTotal * 100) : 0,
            ],
            'upcoming_dates_count' => (int) ($occupancy?->getAttribute('dates_count') ?? 0),
            'next_date_starts_at' => $nextStartsAt !== null
                ? Carbon::parse($nextStartsAt)->toIso8601String()
                : null,
        ];
    }
}
