<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Concerns\LowercasesInput;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantUserRequest extends FormRequest
{
    use LowercasesInput;

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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([
                UserRole::Admin->value,
                UserRole::Operator->value,
                UserRole::Guide->value,
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lowercaseInput('email');
    }
}
