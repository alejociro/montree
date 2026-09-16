<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LogisticsIndexRequest extends FormRequest
{
    private const TABS = ['providers', 'hotels'];

    public function authorize(): bool
    {
        return $this->user()?->can('logistics.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'tab' => ['nullable', 'string', Rule::in(self::TABS)],
            'providers_page' => ['nullable', 'integer', 'min:1'],
            'hotels_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? 'providers';
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'search' => $this->search(),
            'tab' => $this->tab(),
        ];
    }
}
