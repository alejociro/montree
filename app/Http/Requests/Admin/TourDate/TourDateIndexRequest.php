<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\TourDateDisplayStatus;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourDateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Tour::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TourDateDisplayStatus::class)],
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
