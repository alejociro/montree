<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\TenantConfiguration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class TermsRenderer
{
    /**
     * El texto por defecto es un archivo en disco que no cambia durante el
     * request; se lee una vez por idioma y proceso.
     *
     * @var array<string, string>
     */
    private static array $defaultBodies = [];

    public function isDefault(?TenantConfiguration $configuration): bool
    {
        return blank($configuration?->terms_body);
    }

    public function bodyFor(?TenantConfiguration $configuration): string
    {
        if ($this->isDefault($configuration)) {
            return $this->defaultBody();
        }

        return (string) $configuration?->terms_body;
    }

    public function html(string $body): string
    {
        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function defaultBody(): string
    {
        $locale = app()->getLocale();

        return self::$defaultBodies[$locale] ??= $this->readDefaultBody($locale);
    }

    private function readDefaultBody(string $locale): string
    {
        $candidates = array_unique([$locale, (string) config('app.fallback_locale')]);

        foreach ($candidates as $candidate) {
            $path = resource_path("policies/terms.{$candidate}.md");

            if (File::exists($path)) {
                return File::get($path);
            }
        }

        return '';
    }
}
