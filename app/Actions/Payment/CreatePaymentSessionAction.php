<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Contracts\CheckoutClientFactory;
use App\Data\CheckoutContext;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Payment;
use Dnetix\Redirection\Exceptions\PlacetoPayServiceException;
use Dnetix\Redirection\Message\RedirectRequest;
use Dnetix\Redirection\Message\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

final class CreatePaymentSessionAction
{
    public function __construct(private CheckoutClientFactory $clients) {}

    public function handle(Booking $booking, PaymentType $type, ?string $amount, CheckoutContext $context): Payment
    {
        if ($booking->isLocked()) {
            throw BookingException::paymentsLocked();
        }

        $booking->loadMissing(['tenant.configuration', 'tour']);

        $amountToPay = $this->amountToCharge($booking, $type, $amount);

        $live = $this->liveSession($booking, $amountToPay);

        if ($live !== null) {
            return $live;
        }

        $payment = $this->createPending($booking, $this->effectiveType($booking, $type), $amountToPay);

        try {
            $response = $this->requestSession($booking, $payment, $context);
        } catch (PaymentException $exception) {
            $this->markFailed($payment, $exception->getMessage());

            throw $exception;
        }

        $payment->update([
            'request_id' => (string) $response->requestId(),
            'process_url' => $response->processUrl(),
            'status' => PaymentStatus::Processing,
            'gateway_status' => $response->status()->status(),
            'status_message' => Str::limit($response->status()->message(), 250, ''),
            'gateway_response' => $response->toArray(),
        ]);

        return $payment->refresh();
    }

    private function liveSession(Booking $booking, string $amount): ?Payment
    {
        return Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Processing)
            ->where('amount', $amount)
            ->whereNotNull('process_url')
            ->where('session_expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function amountToCharge(Booking $booking, PaymentType $type, ?string $amount): string
    {
        $toCharge = $type === PaymentType::Partial && $amount !== null
            ? $amount
            : $booking->due_amount;

        if (bccomp($toCharge, '0', 2) <= 0) {
            throw PaymentException::nothingDue();
        }

        return $toCharge;
    }

    private function effectiveType(Booking $booking, PaymentType $type): PaymentType
    {
        if ($type === PaymentType::Partial) {
            return PaymentType::Partial;
        }

        return bccomp($booking->paid_amount, '0', 2) > 0
            ? PaymentType::Remainder
            : PaymentType::Full;
    }

    private function createPending(Booking $booking, PaymentType $type, string $amount): Payment
    {
        $payment = Payment::query()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::PlaceToPay,
            'amount' => $amount,
            'currency' => $booking->currency,
            'type' => $type,
            'status' => PaymentStatus::Pending,
            'session_expires_at' => now()->addMinutes(max(5, (int) config('placetopay.expiration_minutes'))),
        ]);

        $payment->update(['reference' => 'MTR-'.$payment->id]);

        return $payment;
    }

    private function requestSession(Booking $booking, Payment $payment, CheckoutContext $context): RedirectResponse
    {
        $client = $this->clients->for($booking->tenant);
        $request = new RedirectRequest($this->payload($booking, $payment, $context));

        try {
            $response = retry(
                (int) config('placetopay.retry.attempts'),
                fn (): RedirectResponse => $client->request($request),
                (int) config('placetopay.retry.wait'),
                fn (Throwable $exception): bool => $exception instanceof PlacetoPayServiceException,
            );
        } catch (Throwable $exception) {
            report($exception);

            throw PaymentException::gatewayUnavailable();
        }

        if (! $response->isSuccessful() || $response->processUrl() === '') {
            logger()->warning('PlacetoPay REQUEST rejected', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'status' => $response->status()->toArray(),
            ]);

            throw PaymentException::sessionRejected($response->status()->message());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Booking $booking, Payment $payment, CheckoutContext $context): array
    {
        $contact = $booking->contact_snapshot ?? [];

        return array_filter([
            'locale' => $context->locale,
            'expiration' => $payment->session_expires_at->toIso8601String(),
            'returnUrl' => URL::signedRoute('payments.return', ['payment' => $payment->id]),
            'ipAddress' => $context->ipAddress,
            'userAgent' => Str::limit($context->userAgent, 250, ''),
            'payment' => [
                'reference' => $payment->reference,
                'description' => $this->description($booking),
                'amount' => [
                    'currency' => $payment->currency,
                    'total' => (float) $payment->amount,
                ],
            ],
            'buyer' => array_filter([
                'name' => $contact['name'] ?? null,
                'email' => $contact['email'] ?? null,
                'mobile' => $contact['phone'] ?? null,
            ]) ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function description(Booking $booking): string
    {
        $text = __('Reserva :number - :tour', [
            'number' => $booking->booking_number,
            'tour' => $booking->tour->name,
        ]);

        $allowed = preg_replace(
            '/[^a-zA-Z0-9ñáéíóúäëïöüàèìòùÑÁÉÍÓÚÄËÏÖÜÀÈÌÒÙÇçÃã\s\[\].,$#&_()\/%+\':;<>|=@-]/u',
            ' ',
            $text,
        ) ?? '';

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $allowed)), 240, '');
    }

    private function markFailed(Payment $payment, string $reason): void
    {
        $payment->update([
            'status' => PaymentStatus::Failed,
            'status_message' => Str::limit($reason, 250, ''),
        ]);
    }
}
