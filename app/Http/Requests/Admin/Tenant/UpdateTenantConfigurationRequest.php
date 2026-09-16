<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenant;

use App\Data\BrandingAssetsData;
use App\Data\TenantConfigurationData;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantConfigurationRequest extends FormRequest
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

        return $this->user()?->can('updateSettings', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', Rule::in(self::SUPPORTED_CURRENCIES)],
            'timezone' => ['sometimes', 'nullable', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(self::SUPPORTED_LOCALES)],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'contact_info' => ['sometimes', 'nullable', 'array'],
            'contact_info.address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_info.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_info.phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'contact_info.whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'reviews_require_moderation' => ['sometimes', 'boolean'],
            'require_traveler_details' => ['sometimes', 'boolean'],
            'custom_css' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'terms_body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'placetopay_login' => ['sometimes', 'nullable', 'string', 'max:60'],
            'placetopay_tran_key' => ['sometimes', 'nullable', 'string', 'max:120'],
            'placetopay_url' => ['sometimes', 'nullable', 'url', 'max:255', 'starts_with:https://'],
            'logo' => ['sometimes', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['sometimes', 'image', 'mimes:png,ico,svg', 'max:1024'],
            'hero_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_hero_image' => ['sometimes', 'boolean'],
        ];
    }

    public function brandingAssets(): BrandingAssetsData
    {
        return BrandingAssetsData::fromRequest($this);
    }

    public function configuration(): TenantConfigurationData
    {
        return TenantConfigurationData::fromRequest($this);
    }

    /**
     * Un texto en blanco equivale a «sin personalizar»: se guarda `null` y vuelve
     * a regir el texto por defecto.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('terms_body')) {
            return;
        }

        $body = $this->input('terms_body');

        $this->merge([
            'terms_body' => blank($body) ? null : trim((string) $body),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSocialLinkKeys($validator);
            $this->validateCheckoutCredentials($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('The primary color must be a valid hex code (e.g. #16a34a).'),
            'secondary_color.regex' => __('The secondary color must be a valid hex code (e.g. #0f766e).'),
            'currency.in' => __('The selected currency is not supported.'),
            'timezone.in' => __('The selected timezone is not valid.'),
            'locale.in' => __('The selected locale is not supported.'),
            'custom_css.max' => __('Custom CSS must be 10000 characters or less.'),
            'terms_body.max' => __('Los términos y condiciones deben tener 20000 caracteres o menos.'),
            'placetopay_url.starts_with' => __('La URL del checkout debe usar https.'),
        ];
    }

    /**
     * El tranKey se puede dejar vacío para conservar el guardado, pero un login
     * nuevo sin tranKey dejaría el comercio a medio configurar y la primera
     * sesión de pago fallaría contra la pasarela en vez de acá.
     */
    private function validateCheckoutCredentials(Validator $validator): void
    {
        if (blank($this->input('placetopay_login'))) {
            return;
        }

        $hasStoredTranKey = filled(Tenant::current()?->configuration?->placetopay_tran_key);

        if (blank($this->input('placetopay_tran_key')) && ! $hasStoredTranKey) {
            $validator->errors()->add('placetopay_tran_key', __('El tranKey es obligatorio para activar el comercio.'));
        }
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
