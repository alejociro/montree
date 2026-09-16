<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TourIndexRequest extends FormRequest
{
    /** Órdenes por columna; el resto los resuelve `TourOperationalSummaryQuery`. */
    private const COLUMN_SORTS = ['created_at', 'name', 'base_price', 'status'];

    private const OPERATIONAL_SORTS = ['next_departure', 'occupancy', 'revenue'];

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
            'status' => ['nullable', 'string', Rule::in(array_column(TourStatus::cases(), 'value'))],
            'category_id' => ['nullable', 'integer', Rule::exists((new Category)->getTable(), 'id')],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', Rule::in([...self::COLUMN_SORTS, ...self::OPERATIONAL_SORTS])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }

    public function categoryId(): ?int
    {
        $categoryId = $this->validated('category_id');

        return $categoryId === null ? null : (int) $categoryId;
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'created_at';
    }

    public function direction(): string
    {
        return $this->validated('direction') ?? 'desc';
    }
}
