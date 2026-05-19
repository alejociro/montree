<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantPlanRequest extends FormRequest
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
            'plan' => ['required', 'string', Rule::in(array_column(TenantPlan::cases(), 'value'))],
        ];
    }

    public function newPlan(): TenantPlan
    {
        return TenantPlan::from((string) $this->validated('plan'));
    }
}
