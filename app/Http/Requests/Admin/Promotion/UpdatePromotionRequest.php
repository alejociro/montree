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

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion
            && ($this->user()?->can('update', $promotion) ?? false);
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
                'sometimes',
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'required', Rule::in(array_column(PromotionType::cases(), 'value'))],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:9999999.99'],
            'max_discount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:9999999.99'],
            'min_amount' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:9999999.99'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'applicable_tours' => ['sometimes', 'nullable', 'array'],
            'applicable_tours.*' => [
                'integer',
                Rule::exists((new Tour)->getTable(), 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $value = $this->input('value');

            if ($type === PromotionType::Percentage->value && $value !== null && (float) $value > 100) {
                $validator->errors()->add('value', 'El porcentaje no puede superar 100.');
            }
        });
    }
}
