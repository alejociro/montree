<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\ProcessPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentController extends Controller
{
    public function __construct(private ProcessPaymentAction $processPayment) {}

    public function store(ProcessPaymentRequest $request, string $bookingNumber): JsonResponse
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        $payment = $this->processPayment->handle($booking, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
