<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantConfigurationRequest extends FormRequest
{
    private const SUPPORTED_CURRENCIES = ['USD', 'COP', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'];

    private const SUPPORTED_LOCALES = ['es', 'en'];

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
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', Rule::in(self::SUPPORTED_CURRENCIES)],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'locale' => ['nullable', 'string', Rule::in(self::SUPPORTED_LOCALES)],
            'reviews_require_moderation' => ['sometimes', 'boolean'],
            'require_traveler_details' => ['sometimes', 'boolean'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'contact_info' => ['nullable', 'array'],
            'contact_info.*' => ['nullable', 'string', 'max:255'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
            'min_partial_payment_pct' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
