<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Catalog\PublicTourResource;
use App\Models\Booking;
use App\Models\TourDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BookingPagesController extends Controller
{
    public function create(Request $request): Response
    {
        $tourDateId = (int) $request->query('tour_date_id', 0);

        $tourDate = TourDate::query()
            ->with(['tour' => fn ($q) => $q->with('images', 'category')])
            ->find($tourDateId);

        if ($tourDate === null) {
            throw new NotFoundHttpException('Tour date not found.');
        }

        $requireTravelers = (bool) ($tourDate->tour->tenant->configuration->require_traveler_details ?? false);

        return Inertia::render('Booking/Create', [
            'tour' => (new PublicTourResource($tourDate->tour->load(['images', 'category', 'itineraries', 'dates' => fn ($q) => $q->where('id', $tourDateId)])))->resolve($request),
            'tourDate' => [
                'id' => $tourDate->id,
                'starts_at' => $tourDate->starts_at->toIso8601String(),
                'ends_at' => $tourDate->ends_at?->toIso8601String(),
                'price_override' => $tourDate->price_override,
                'effective_price' => $tourDate->price_override ?? $tourDate->tour->base_price,
                'available_seats' => max(0, $tourDate->capacity - $tourDate->booked_count),
                'currency' => $tourDate->tour->currency,
            ],
            'requireTravelers' => $requireTravelers,
        ]);
    }

    public function show(Request $request, string $bookingNumber): Response
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with(['tour', 'tourDate', 'travelers', 'promotion'])
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        return Inertia::render('Booking/Show', [
            'booking' => [
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'total_amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'tour_name' => $booking->tour->name,
                'starts_at' => $booking->tourDate->starts_at->toIso8601String(),
                'travelers_count' => $booking->travelers_count,
            ],
        ]);
    }
}
