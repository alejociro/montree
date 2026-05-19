<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CreateBookingAction;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BookingController extends Controller
{
    public function __construct(private CreateBookingAction $createBooking) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->createBooking->handle($request->user(), $request->validated());

        return (new BookingResource($booking))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $bookingNumber): BookingResource
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with(['tour', 'tourDate', 'travelers', 'promotion'])
            ->first();

        if ($booking === null) {
            throw BookingException::notFound();
        }

        return new BookingResource($booking);
    }
}
