<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Concerns\LowercasesInput;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    use LowercasesInput;

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
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lowercaseInput('slug', 'admin_email');
    }
}
