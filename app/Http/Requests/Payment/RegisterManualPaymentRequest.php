<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Models\Booking;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class RegisterManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:180'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $booking = $this->booking();

            if ($booking === null || bccomp((string) $this->input('amount'), $booking->due_amount, 2) <= 0) {
                return;
            }

            $validator->errors()->add('amount', __('El monto supera el saldo pendiente de la reserva.'));
        });
    }

    public function amount(): string
    {
        return number_format((float) $this->validated('amount'), 2, '.', '');
    }

    public function paidAt(): ?Carbon
    {
        $paidAt = $this->validated('paid_at');

        return $paidAt === null ? null : Carbon::parse((string) $paidAt);
    }

    private function booking(): ?Booking
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking ? $booking : null;
    }
}
