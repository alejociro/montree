<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('categories.manage') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            // WHY: `Rule::exists` ignora el global scope del tenant, así que un id ajeno
            // pasaría la validación y luego no se encontraría. La lista sale del scope.
            'ids.*' => ['integer', Rule::in(Category::query()->pluck('id')->all())],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = $this->validated('ids');

        return array_map(intval(...), array_values($ids));
    }
}
