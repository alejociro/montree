<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Passengers\StorePassengerAction;
use App\Actions\Passengers\UpdatePassengerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\StorePassengerRequest;
use App\Http\Requests\Passenger\UpdatePassengerRequest;
use App\Http\Resources\Passenger\PassengerResource;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PassengerController extends Controller
{
    public function __construct(
        private StorePassengerAction $storePassenger,
        private UpdatePassengerAction $updatePassenger,
    ) {}

    public function store(StorePassengerRequest $request, Booking $booking): JsonResponse
    {
        $passenger = $this->storePassenger->handle($booking->load('tourDate:id,starts_at'), $request->validated());

        return (new PassengerResource($passenger))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePassengerRequest $request, BookingTraveler $traveler): PassengerResource
    {
        $booking = $traveler->booking()->with('tourDate:id,starts_at')->firstOrFail();

        return new PassengerResource($this->updatePassenger->handle($booking, $traveler, $request->validated()));
    }
}
