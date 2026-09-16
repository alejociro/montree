<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\DepartureScope;
use App\Enums\TourDateDisplayStatus;
use App\Models\Tenant;
use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DepartureIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TourDate::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TourDateDisplayStatus::class)],
            'scope' => ['nullable', Rule::enum(DepartureScope::class)],
            'search' => ['nullable', 'string', 'max:120'],
            'tour_id' => ['nullable', 'integer', Rule::exists('tours', 'id')->where('tenant_id', $this->tenantId())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function displayStatus(): ?TourDateDisplayStatus
    {
        $status = (string) $this->validated('status');

        return $status === '' ? null : TourDateDisplayStatus::from($status);
    }

    public function scope(): DepartureScope
    {
        $scope = (string) $this->validated('scope');

        return $scope === '' ? DepartureScope::All : DepartureScope::from($scope);
    }

    public function searchTerm(): ?string
    {
        $term = trim((string) $this->validated('search'));

        return $term === '' ? null : $term;
    }

    public function tourId(): ?int
    {
        $tourId = (int) $this->validated('tour_id');

        return $tourId > 0 ? $tourId : null;
    }

    public function from(): ?string
    {
        return $this->validated('from');
    }

    public function to(): ?string
    {
        return $this->validated('to');
    }

    public function sortDirection(): string
    {
        return $this->validated('direction') === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'status' => $this->displayStatus()?->value,
            'scope' => $this->scope()->value,
            'search' => $this->searchTerm(),
            'tour_id' => $this->tourId(),
            'from' => $this->from(),
            'to' => $this->to(),
            'direction' => $this->sortDirection(),
        ];
    }

    private function tenantId(): int
    {
        return Tenant::current()?->getKey() ?? 0;
    }
}
