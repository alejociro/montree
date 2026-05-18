<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    public function profile(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user(), Tenant::current());
    }

    public function updateProfile(UpdateProfileRequest $request): AuthUserResource
    {
        $request->user()->fill($request->validated())->save();

        return new AuthUserResource($request->user()->fresh(), Tenant::current());
    }

    public function bookings(Request $request): JsonResponse
    {
        $user = $request->user();

        $upcoming = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
            ->whereHas('tourDate', fn ($q) => $q->where('starts_at', '>=', now()))
            ->with(['tour:id,slug,name', 'tourDate:id,starts_at,ends_at,tour_id'])
            ->orderBy('id', 'desc')
            ->get();

        $past = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::Completed, BookingStatus::Confirmed])
            ->whereHas('tourDate', fn ($q) => $q->where('starts_at', '<', now()))
            ->with(['tour:id,slug,name', 'tourDate:id,starts_at,ends_at,tour_id'])
            ->orderBy('id', 'desc')
            ->get();

        $cancelled = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::Expired])
            ->with(['tour:id,slug,name', 'tourDate:id,starts_at,ends_at,tour_id'])
            ->orderBy('id', 'desc')
            ->get();

        return new JsonResponse([
            'data' => [
                'upcoming' => $upcoming->map(fn ($b) => $this->bookingSummary($b)),
                'past' => $past->map(fn ($b) => $this->bookingSummary($b)),
                'cancelled' => $cancelled->map(fn ($b) => $this->bookingSummary($b)),
            ],
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with(['tour' => fn ($q) => $q->with('coverImage')])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn ($f) => $f->tour !== null)
            ->map(function ($f) {
                $cover = $f->tour->coverImage;

                return [
                    'id' => $f->id,
                    'tour' => [
                        'id' => $f->tour->id,
                        'slug' => $f->tour->slug,
                        'name' => $f->tour->name,
                        'base_price' => $f->tour->base_price,
                        'currency' => $f->tour->currency,
                        'rating_average' => $f->tour->rating_average,
                        'cover_image_url' => $cover?->path,
                        'is_available' => $f->tour->status->value === 'active',
                    ],
                ];
            })
            ->values();

        return new JsonResponse(['data' => $favorites]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingSummary(Booking $booking): array
    {
        return [
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'travelers_count' => $booking->travelers_count,
            'tour' => [
                'id' => $booking->tour->id,
                'slug' => $booking->tour->slug,
                'name' => $booking->tour->name,
            ],
            'starts_at' => $booking->tourDate->starts_at->toIso8601String(),
            'expires_at' => $booking->expires_at?->toIso8601String(),
        ];
    }
}
