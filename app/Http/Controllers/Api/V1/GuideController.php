<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuideController extends Controller
{
    public function schedule(Request $request): JsonResponse
    {
        $dates = TourDate::query()
            ->where('guide_id', $request->user()->id)
            ->where('starts_at', '>=', now())
            ->with('tour:id,name,slug')
            ->orderBy('starts_at')
            ->get();

        return new JsonResponse([
            'data' => $dates->map(fn ($d) => [
                'id' => $d->id,
                'starts_at' => $d->starts_at->toIso8601String(),
                'ends_at' => $d->ends_at?->toIso8601String(),
                'capacity_total' => $d->capacity,
                'capacity_booked' => $d->booked_count,
                'tour' => [
                    'id' => $d->tour->id,
                    'name' => $d->tour->name,
                    'slug' => $d->tour->slug,
                ],
            ])->values(),
        ]);
    }

    public function travelers(Request $request, TourDate $tourDate): JsonResponse
    {
        if ($tourDate->guide_id !== $request->user()->id) {
            abort(403);
        }

        $bookings = Booking::query()
            ->where('tour_date_id', $tourDate->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed])
            ->with('travelers', 'user:id,name,email,phone')
            ->get();

        return new JsonResponse([
            'data' => $bookings->map(fn ($b) => [
                'booking_number' => $b->booking_number,
                'customer' => ['name' => $b->user->name, 'email' => $b->user->email, 'phone' => $b->user->phone],
                'travelers_count' => $b->travelers_count,
                'travelers' => $b->travelers->map(fn ($t) => ['full_name' => $t->full_name, 'email' => $t->email, 'phone' => $t->phone])->values(),
            ])->values(),
        ]);
    }
}
