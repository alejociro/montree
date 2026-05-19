<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenant;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantConfigurationRequest extends FormRequest
{
    private const SUPPORTED_CURRENCIES = ['USD', 'COP', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'];

    private const SUPPORTED_LOCALES = ['es', 'en'];

    private const SOCIAL_LINK_KEYS = ['instagram', 'facebook', 'twitter', 'youtube', 'tiktok'];

    public function authorize(): bool
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return false;
        }

        return $this->user()?->can('update', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(self::SUPPORTED_CURRENCIES)],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => ['nullable', 'string', Rule::in(self::SUPPORTED_LOCALES)],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'contact_info' => ['nullable', 'array'],
            'reviews_require_moderation' => ['nullable', 'boolean'],
            'require_traveler_details' => ['nullable', 'boolean'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSocialLinkKeys($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'The primary color must be a valid hex code (e.g. #16a34a).',
            'secondary_color.regex' => 'The secondary color must be a valid hex code (e.g. #0f766e).',
            'currency.in' => 'The selected currency is not supported.',
            'timezone.in' => 'The selected timezone is not valid.',
            'locale.in' => 'The selected locale is not supported.',
            'custom_css.max' => 'Custom CSS must be 10000 characters or less.',
        ];
    }

    private function validateSocialLinkKeys(Validator $validator): void
    {
        $links = $this->input('social_links');

        if (! is_array($links)) {
            return;
        }

        foreach (array_keys($links) as $key) {
            if (! in_array($key, self::SOCIAL_LINK_KEYS, true)) {
                $validator->errors()->add('social_links', "Unsupported social network: {$key}.");
            }
        }
    }
}
