<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;

final class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:'.implode(',', array_map(fn ($c) => $c->value, PaymentType::cases()))],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ];
    }
}
