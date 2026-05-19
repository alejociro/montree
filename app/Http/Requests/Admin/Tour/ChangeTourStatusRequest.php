<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Enums\TourStatus;
use App\Models\Tour;
use App\Services\Tour\TourStatusTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTourStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tour = $this->route('tour');

        if (! $tour instanceof Tour) {
            return false;
        }

        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (! $user->can('update', $tour)) {
            return false;
        }

        $next = $this->resolveNextStatus();

        if ($next === null) {
            return true;
        }

        return ! app(TourStatusTransition::class)->requiresAdmin($next)
            || $user->can('archive', $tour);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(TourStatus::cases(), 'value'))],
        ];
    }

    public function nextStatus(): TourStatus
    {
        return TourStatus::from((string) $this->validated('status'));
    }

    private function resolveNextStatus(): ?TourStatus
    {
        $value = $this->input('status');

        if (! is_string($value)) {
            return null;
        }

        return TourStatus::tryFrom($value);
    }
}
