<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Catalog\PublicTourResource;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $authUser = $request->user();

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
            'prefill' => $authUser !== null ? [
                'email' => $authUser->email,
                'full_name' => $authUser->name,
                'phone' => $authUser->phone ?? '',
            ] : null,
        ]);
    }

    public function show(Request $request, string $bookingNumber): Response
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with(['tour.coverImage', 'tourDate', 'travelers', 'promotion'])
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        $coverImageUrl = null;
        if ($booking->tour->coverImage !== null) {
            $coverImageUrl = str_starts_with((string) $booking->tour->coverImage->path, 'http')
                ? $booking->tour->coverImage->path
                : Storage::disk('public')->url($booking->tour->coverImage->path);
        }

        $newAccount = $request->session()->pull('booking_new_account', false);

        $requireTravelerDetails = (bool) (Tenant::current()?->configuration?->require_traveler_details ?? false);

        return Inertia::render('Booking/Show', [
            'new_account' => $newAccount,
            'require_traveler_details' => $requireTravelerDetails,
            'booking' => [
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'travelers_count' => $booking->travelers_count,
                'adults_count' => $booking->adults_count,
                'minors_count' => $booking->minors_count,
                'subtotal' => $booking->subtotal,
                'discount_amount' => $booking->discount_amount,
                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
                'currency' => $booking->currency,
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'contact_snapshot' => $booking->contact_snapshot,
                'tour' => [
                    'name' => $booking->tour->name,
                    'slug' => $booking->tour->slug,
                    'meeting_point' => $booking->tour->meeting_point,
                    'cover_image_url' => $coverImageUrl,
                ],
                'tour_date' => [
                    'starts_at' => $booking->tourDate->starts_at->toIso8601String(),
                    'ends_at' => $booking->tourDate->ends_at?->toIso8601String(),
                ],
                'travelers' => $booking->travelers->map(fn ($traveler) => [
                    'id' => $traveler->id,
                    'full_name' => $traveler->full_name,
                    'is_minor' => $traveler->is_minor,
                    'email' => $traveler->email,
                    'phone' => $traveler->phone,
                    'document_type' => $traveler->document_type,
                    'document_number' => $traveler->document_number,
                    'birth_date' => $traveler->birth_date?->toDateString(),
                ])->values(),
            ],
        ]);
    }
}
