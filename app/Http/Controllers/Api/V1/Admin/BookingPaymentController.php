<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payments\RegisterManualPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\RegisterManualPaymentRequest;
use App\Http\Resources\Payment\BookingBalanceResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BookingPaymentController extends Controller
{
    public function __construct(private RegisterManualPaymentAction $registerPayment) {}

    public function store(RegisterManualPaymentRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->registerPayment->handle(
            $booking,
            $request->amount(),
            $request->validated('reference'),
            $request->paidAt(),
        );

        return (new BookingBalanceResource($booking))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
