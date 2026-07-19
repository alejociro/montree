<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Enums\TenantPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:63',
                'regex:/^[a-z0-9][a-z0-9-]{1,62}$/',
                Rule::notIn(['www', 'admin', 'app', 'api', 'mail', 'montree']),
                'unique:tenants,slug',
            ],
            'plan' => ['required', 'string', Rule::in(array_column(TenantPlan::cases(), 'value'))],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => mb_strtolower(trim((string) $this->input('slug')))]);
        }

        if ($this->has('admin_email')) {
            $this->merge(['admin_email' => mb_strtolower(trim((string) $this->input('admin_email')))]);
        }
    }

    public function plan(): TenantPlan
    {
        return TenantPlan::from((string) $this->validated('plan'));
    }
}
