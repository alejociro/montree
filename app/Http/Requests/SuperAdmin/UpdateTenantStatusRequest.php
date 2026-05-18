<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_column(TenantStatus::cases(), 'value'))],
            'reason' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => $this->input('status') === TenantStatus::Suspended->value),
            ],
        ];
    }

    public function nextStatus(): TenantStatus
    {
        return TenantStatus::from((string) $this->validated('status'));
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
