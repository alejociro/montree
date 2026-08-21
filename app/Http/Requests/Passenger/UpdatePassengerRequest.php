<?php

declare(strict_types=1);

namespace App\Http\Requests\Passenger;

use App\Http\Requests\Passenger\Concerns\PassengerPayload;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePassengerRequest extends FormRequest
{
    use PassengerPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->passenger()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->passengerRules(isMinorRequired: false);
    }

    protected function prepareForValidation(): void
    {
        $this->maskMedicalInput($this->passenger());
    }

    private function passenger(): BookingTraveler
    {
        $passenger = $this->route('traveler');

        abort_unless($passenger instanceof BookingTraveler, 404);

        return $passenger;
    }
}
