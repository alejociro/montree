<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Data\CheckoutContext;
use App\Enums\PaymentType;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Valida el monto contra los límites que ya trae la reserva —que a su vez
 * vienen del precio configurado en la salida—. La Action no recalcula nada.
 */
final class StartPaymentRequest extends FormRequest
{
    private ?Booking $booking = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->booking() === null) {
            throw new NotFoundHttpException(__('No encontramos la reserva indicada.'));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Booking $booking */
        $booking = $this->booking();

        return [
            'type' => ['required', Rule::enum(PaymentType::class)->only([PaymentType::Full, PaymentType::Partial])],
            'amount' => [
                'nullable',
                'required_if:type,partial',
                'numeric',
                'min:'.$booking->min_payment_amount,
                'max:'.$booking->due_amount,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var Booking $booking */
        $booking = $this->booking();

        return [
            'amount.min' => __('El abono mínimo es :amount :currency.', [
                'amount' => $booking->min_payment_amount,
                'currency' => $booking->currency,
            ]),
            'amount.max' => __('El monto supera el saldo pendiente de :amount :currency.', [
                'amount' => $booking->due_amount,
                'currency' => $booking->currency,
            ]),
        ];
    }

    public function booking(): ?Booking
    {
        if ($this->booking !== null) {
            return $this->booking;
        }

        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return $this->booking = Booking::query()
            ->where('booking_number', $this->route('bookingNumber'))
            ->where('user_id', $user->id)
            ->with(['tenant.configuration', 'tour', 'tourDate'])
            ->first();
    }

    public function paymentType(): PaymentType
    {
        return PaymentType::from($this->string('type')->value());
    }

    public function paymentAmount(): ?string
    {
        $amount = $this->input('amount');

        return $amount === null ? null : number_format((float) $amount, 2, '.', '');
    }

    public function checkoutContext(): CheckoutContext
    {
        return new CheckoutContext(
            ipAddress: (string) $this->ip(),
            userAgent: (string) ($this->userAgent() ?? 'unknown'),
            locale: $this->checkoutLocale(),
        );
    }

    private function checkoutLocale(): string
    {
        return match (app()->getLocale()) {
            'en' => 'en_US',
            default => 'es_CO',
        };
    }
}
