<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class TopToursResolver
{
    public function for(Carbon $start, Carbon $end, int $limit = 5): Collection
    {
        $bookingsByTour = Booking::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
                BookingStatus::PendingPayment->value,
            ])
            ->selectRaw('tour_id, COUNT(*) as bookings_count')
            ->groupBy('tour_id')
            ->orderByDesc('bookings_count')
            ->limit($limit)
            ->pluck('bookings_count', 'tour_id');

        if ($bookingsByTour->isEmpty()) {
            return collect();
        }

        $tourIds = $bookingsByTour->keys()->all();

        $revenueByTour = Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', PaymentStatus::Completed->value)
            ->whereBetween('payments.processed_at', [$start, $end])
            ->whereIn('bookings.tour_id', $tourIds)
            ->selectRaw('bookings.tour_id as tour_id, SUM(payments.amount) as total')
            ->groupBy('bookings.tour_id')
            ->pluck('total', 'tour_id');

        $tours = Tour::query()
            ->whereIn('id', $tourIds)
            ->with(['images' => fn ($query) => $query->where('is_cover', true)->limit(1)])
            ->get()
            ->keyBy('id');

        return collect($tourIds)
            ->map(function (int $tourId) use ($tours, $bookingsByTour, $revenueByTour): ?array {
                $tour = $tours->get($tourId);

                if ($tour === null) {
                    return null;
                }

                $cover = $tour->images->first();

                return [
                    'id' => $tour->id,
                    'slug' => $tour->slug,
                    'name' => $tour->name,
                    'bookings_count' => (int) $bookingsByTour->get($tourId),
                    'revenue' => number_format((float) ($revenueByTour->get($tourId) ?? 0), 2, '.', ''),
                    'rating_average' => (string) $tour->rating_average,
                    'cover_image_url' => $cover !== null ? $this->resolveImageUrl($cover->path) : null,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }
}
