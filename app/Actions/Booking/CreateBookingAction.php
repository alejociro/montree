<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Actions\Promotion\ValidatePromotionAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use App\Enums\TourDateStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateBookingAction
{
    private const HOLD_MINUTES = 30;

    public function __construct(private ValidatePromotionAction $validatePromotion) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data): Booking {
            $tourDate = TourDate::query()
                ->lockForUpdate()
                ->with('tour')
                ->find((int) $data['tour_date_id']);

            if ($tourDate === null || $tourDate->status !== TourDateStatus::Open) {
                throw BookingException::dateNotAvailable();
            }

            if ($tourDate->starts_at->isPast()) {
                throw BookingException::bookingWindowClosed();
            }

            $travelers = (int) $data['travelers_count'];
            $available = $tourDate->capacity - $tourDate->booked_count;
            if ($available < $travelers) {
                throw BookingException::insufficientCapacity($available);
            }

            $pricePerSeat = (string) ($tourDate->price_override ?? $tourDate->tour->base_price);
            $subtotal = bcmul($pricePerSeat, (string) $travelers, 2);

            $discount = '0.00';
            $promotionId = null;
            if (! empty($data['promotion_code'])) {
                $result = $this->validatePromotion->handle(
                    (string) $data['promotion_code'],
                    $tourDate,
                    $subtotal,
                    $user,
                );
                $discount = $result->discount;
                $promotionId = $result->promotionId;
            }

            $total = bcsub($subtotal, $discount, 2);
            if (bccomp($total, '1.00', 2) < 0) {
                $total = '1.00';
            }

            $booking = Booking::query()->create([
                'user_id' => $user->id,
                'tour_id' => $tourDate->tour_id,
                'tour_date_id' => $tourDate->id,
                'promotion_id' => $promotionId,
                'travelers_count' => $travelers,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'paid_amount' => '0.00',
                'currency' => $tourDate->tour->currency,
                'status' => BookingStatus::PendingPayment,
                'payment_type' => PaymentType::Full,
                'special_requests' => $data['special_requests'] ?? null,
                'contact_snapshot' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                ],
                'expires_at' => now()->addMinutes(self::HOLD_MINUTES),
            ]);

            foreach ($data['travelers'] ?? [] as $traveler) {
                BookingTraveler::query()->create([
                    'booking_id' => $booking->id,
                    'full_name' => $traveler['full_name'],
                    'document_type' => $traveler['document_type'] ?? null,
                    'document_number' => $traveler['document_number'] ?? null,
                    'email' => $traveler['email'] ?? null,
                    'phone' => $traveler['phone'] ?? null,
                ]);
            }

            $tourDate->increment('booked_count', $travelers);
            if ($tourDate->booked_count + 0 >= $tourDate->capacity) {
                $tourDate->status = TourDateStatus::Full;
                $tourDate->save();
            }

            return $booking->fresh(['tour', 'tourDate', 'travelers', 'promotion']);
        });
    }
}
