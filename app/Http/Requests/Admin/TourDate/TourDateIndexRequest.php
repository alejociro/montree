<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\DepartureScope;
use App\Enums\TourDateDisplayStatus;
use App\Models\Tenant;
use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourDateIndexRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(TourDateDisplayStatus::class)],
            'scope' => ['sometimes', Rule::enum(DepartureScope::class)],
            'search' => ['sometimes', 'string', 'max:120'],
            'tour_id' => ['sometimes', 'integer', Rule::exists('tours', 'id')->where('tenant_id', $this->tenantId())],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    public function displayStatus(): ?TourDateDisplayStatus
    {
        $status = $this->string('status')->toString();

        return $status === '' ? null : TourDateDisplayStatus::from($status);
    }

    public function scope(): DepartureScope
    {
        $scope = $this->string('scope')->toString();

        return $scope === '' ? DepartureScope::All : DepartureScope::from($scope);
    }

    public function searchTerm(): ?string
    {
        $term = trim($this->string('search')->toString());

        return $term === '' ? null : $term;
    }

    public function tourId(): ?int
    {
        $tourId = (int) $this->integer('tour_id');

        return $tourId > 0 ? $tourId : null;
    }

    public function sortDirection(): string
    {
        return $this->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }

    protected function tenantId(): int
    {
        return Tenant::current()?->getKey() ?? 0;
    }
}
