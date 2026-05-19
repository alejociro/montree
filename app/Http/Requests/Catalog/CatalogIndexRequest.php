<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\TourDifficulty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogIndexRequest extends FormRequest
{
    public const SORTS = [
        'price_asc',
        'price_desc',
        'rating_desc',
        'newest',
        'next_date_asc',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['nullable', 'string', Rule::in(array_column(TourDifficulty::cases(), 'value'))],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'gte:price_min'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     search: string|null,
     *     category: string|null,
     *     difficulty: string|null,
     *     price_min: float|null,
     *     price_max: float|null,
     *     sort: string|null,
     *     per_page: int|null,
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? (string) $validated['search'] : null,
            'category' => isset($validated['category']) ? (string) $validated['category'] : null,
            'difficulty' => isset($validated['difficulty']) ? (string) $validated['difficulty'] : null,
            'price_min' => isset($validated['price_min']) ? (float) $validated['price_min'] : null,
            'price_max' => isset($validated['price_max']) ? (float) $validated['price_max'] : null,
            'sort' => isset($validated['sort']) ? (string) $validated['sort'] : null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
