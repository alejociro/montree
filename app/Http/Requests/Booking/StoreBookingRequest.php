<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Tenant;
use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Tenant::current() !== null && $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $requireTravelers = (bool) (Tenant::current()?->configuration?->require_traveler_details ?? false);

        return [
            'tour_date_id' => ['required', 'integer', 'exists:tour_dates,id'],
            'travelers_count' => ['required', 'integer', 'min:1', 'max:50'],
            'promotion_code' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'travelers' => [$requireTravelers ? 'required' : 'nullable', 'array'],
            'travelers.*.full_name' => ['required_with:travelers', 'string', 'max:120'],
            'travelers.*.document_type' => ['nullable', 'string', 'max:20'],
            'travelers.*.document_number' => ['nullable', 'string', 'max:40'],
            'travelers.*.email' => ['nullable', 'email', 'max:255'],
            'travelers.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            $tourDateId = (int) $this->input('tour_date_id', 0);
            $tourDate = TourDate::query()->find($tourDateId);
            if ($tourDate === null) {
                return;
            }
            $tenantId = Tenant::current()?->id;
            if ($tourDate->tenant_id !== $tenantId) {
                $v->errors()->add('tour_date_id', 'La fecha no pertenece a este tenant.');
            }
        });
    }
}
