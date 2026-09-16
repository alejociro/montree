<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Enums\CommissionType;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $this->user()?->can('manage-platform-tenant', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(array_column(CommissionType::cases(), 'value'))],
            'value' => [
                'required_with:type',
                'nullable',
                'numeric',
                'min:0',
                Rule::when(
                    $this->input('type') === CommissionType::Percentage->value,
                    ['max:100'],
                ),
            ],
        ];
    }

    public function commissionType(): ?CommissionType
    {
        $type = $this->validated('type');

        return $type === null ? null : CommissionType::from((string) $type);
    }

    public function commissionValue(): ?string
    {
        if ($this->commissionType() === null) {
            return null;
        }

        return number_format((float) $this->validated('value'), 2, '.', '');
    }
}
