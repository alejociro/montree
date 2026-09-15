<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

final class NotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'requestId' => ['required'],
            'reference' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'status' => ['required', 'array'],
            'status.status' => ['required', 'string'],
            'status.date' => ['required', 'string'],
        ];
    }

    /**
     * @return array{requestId: string, status: string, date: string, signature: string}
     */
    public function notification(): array
    {
        return [
            'requestId' => (string) $this->input('requestId'),
            'status' => (string) $this->input('status.status'),
            'date' => (string) $this->input('status.date'),
            'signature' => (string) $this->input('signature'),
        ];
    }
}
