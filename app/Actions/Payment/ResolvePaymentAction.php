<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Contracts\CheckoutClientFactory;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmedNotification;
use App\Services\Payments\BookingSettlementService;
use Carbon\CarbonInterface;
use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Message\RedirectInformation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ResolvePaymentAction
{
    public function __construct(
        private CheckoutClientFactory $clients,
        private BookingSettlementService $settlement,
    ) {}

    public function handle(Payment $payment): Payment
    {
        if ($payment->isResolved() || $payment->request_id === null) {
            return $payment;
        }

        $payment->loadMissing(['tenant', 'booking.user']);

        $information = $this->clients->for($payment->tenant)->query((int) $payment->request_id);

        if (! $information->isSuccessful()) {
            logger()->warning('PlacetoPay QUERY failed', [
                'payment_id' => $payment->id,
                'request_id' => $payment->request_id,
                'status' => $information->status()->toArray(),
            ]);

            return $payment;
        }

        return $this->apply($payment, $information);
    }

    private function apply(Payment $payment, RedirectInformation $information): Payment
    {
        $transaction = $information->lastApprovedTransaction() ?? $information->lastTransaction();
        $status = $this->mapStatus($information->status()->status());

        return DB::transaction(function () use ($payment, $information, $transaction, $status): Payment {
            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->isResolved()) {
                return $fresh ?? $payment;
            }

            $fresh->fill([
                'status' => $status,
                'gateway_status' => $information->status()->status(),
                'status_message' => Str::limit(
                    $transaction?->status()->message() ?: $information->status()->message(),
                    250,
                    '',
                ),
                'gateway_response' => $this->snapshot($information),
                'processed_at' => in_array($status, [PaymentStatus::Completed, PaymentStatus::Refunded], true)
                    ? $this->transactionDate($transaction)
                    : null,
            ]);

            if ($transaction !== null) {
                $this->fillFromTransaction($fresh, $transaction);
            }

            if ($status === PaymentStatus::Completed) {
                $approved = $this->approvedTotal($information);

                if ($approved !== null) {
                    $fresh->amount = $approved;
                }
            }

            $fresh->save();

            if ($status === PaymentStatus::Completed) {
                $this->settleBooking($fresh);
            }

            return $fresh->refresh();
        });
    }

    private function fillFromTransaction(Payment $payment, Transaction $transaction): void
    {
        $payment->fill([
            'internal_reference' => $transaction->internalReference() ?: null,
            'authorization' => $transaction->authorization() ?: null,
            'receipt' => $transaction->receipt() ?: null,
            'franchise' => $transaction->franchise() ?: null,
            'payment_method' => $transaction->paymentMethod() ?: null,
            'payment_method_name' => $transaction->paymentMethodName() ?: null,
            'issuer_name' => $transaction->issuerName() ?: null,
            'processor_fields' => $transaction->processorFieldsToArray() ?: null,
        ]);
    }

    private function transactionDate(?Transaction $transaction): CarbonInterface
    {
        $date = $transaction?->status()->date();

        if (blank($date)) {
            return now();
        }

        try {
            return Carbon::parse((string) $date)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return now();
        }
    }

    /** Suma de lo aprobado: en un aprobado parcial no coincide con lo pedido. */
    private function approvedTotal(RedirectInformation $information): ?string
    {
        $total = '0.00';

        foreach ($information->payment() as $transaction) {
            if (! $transaction->isApproved()) {
                continue;
            }

            try {
                $total = bcadd($total, (string) $transaction->amount()->from()->total(), 2);
            } catch (Throwable $exception) {
                report($exception);

                return null;
            }
        }

        return bccomp($total, '0', 2) > 0 ? $total : null;
    }

    /**
     * El snapshot es para auditoría: si la librería revienta al serializar, no
     * puede tumbar la resolución del pago.
     *
     * @return array<string, mixed>
     */
    private function snapshot(RedirectInformation $information): array
    {
        try {
            return $information->toArray();
        } catch (Throwable $exception) {
            report($exception);

            return [
                'requestId' => $information->requestId(),
                'status' => $information->status()->toArray(),
            ];
        }
    }

    private function mapStatus(string $gatewayStatus): PaymentStatus
    {
        return match ($gatewayStatus) {
            Status::ST_APPROVED, Status::ST_APPROVED_PARTIAL => PaymentStatus::Completed,
            Status::ST_REFUNDED => PaymentStatus::Refunded,
            Status::ST_OK, Status::ST_PENDING, Status::ST_PENDING_VALIDATION => PaymentStatus::Processing,
            default => PaymentStatus::Failed,
        };
    }

    private function settleBooking(Payment $payment): void
    {
        $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->first();

        if ($booking === null) {
            return;
        }

        $result = $this->settlement->apply($booking, $payment);

        if (! $result->wasJustConfirmed) {
            return;
        }

        $result->booking->user->notify(BookingConfirmedNotification::fromBooking($result->booking));
    }
}
