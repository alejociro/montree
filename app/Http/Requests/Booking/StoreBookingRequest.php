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
        return Tenant::current() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isGuest = $this->user() === null;

        return [
            // Guest personal info (required when not authenticated)
            'email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'email_confirmation' => [$isGuest ? 'required' : 'nullable', 'string', 'max:255', 'same:email'],
            'full_name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:120'],
            'phone' => [$isGuest ? 'required' : 'nullable', 'string', 'max:30'],

            // Booking info
            'tour_date_id' => ['required', 'integer', 'exists:tour_dates,id'],
            'travelers_count' => ['required', 'integer', 'min:1', 'max:50'],
            'promotion_code' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:1000'],

            // Emergency contact
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            // Traveler mode: complete_now = fill all travelers inline, share_link = send link later
            'traveler_mode' => ['nullable', 'string', 'in:complete_now,share_link'],

            // Traveler details (when traveler_mode = complete_now)
            'travelers' => ['nullable', 'array'],
            'travelers.*.full_name' => ['required_with:travelers', 'string', 'max:120'],
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
