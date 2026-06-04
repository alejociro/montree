<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Promotion;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Promotion::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code') && is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenant = Tenant::current();
        $tenantId = $tenant?->id ?? 0;

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(array_column(PromotionType::cases(), 'value'))],
            'value' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:9999999.99'],
            'min_amount' => ['nullable', 'numeric', 'gte:0', 'max:9999999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'applicable_tours' => ['nullable', 'array'],
            'applicable_tours.*' => [
                'integer',
                Rule::exists((new Tour)->getTable(), 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === PromotionType::Percentage->value
                && $this->filled('value')
                && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', 'El porcentaje no puede superar 100.');
            }
        });
    }
}
