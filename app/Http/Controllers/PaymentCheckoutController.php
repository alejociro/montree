<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payment\CreatePaymentSessionAction;
use App\Http\Requests\Payment\StartPaymentRequest;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PaymentCheckoutController extends Controller
{
    public function __construct(private CreatePaymentSessionAction $createSession) {}

    public function store(StartPaymentRequest $request): Response
    {
        $payment = $this->createSession->handle(
            $request->booking(),
            $request->paymentType(),
            $request->paymentAmount(),
            $request->checkoutContext(),
        );

        return Inertia::location((string) $payment->process_url);
    }
}
