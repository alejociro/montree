<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return false;
        }

        return $this->user()?->can('update', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
