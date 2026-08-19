<?php

declare(strict_types=1);

namespace Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaLocalePropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_includes_locale_locales_and_translations(): void
    {
        $this->withHeader('Accept-Language', 'es')->get('/')->assertInertia(fn ($page) => $page
            ->where('locale', 'es')
            ->has('locales', 2)
            ->where('locales.0.code', 'es')
            ->where('locales.1.code', 'en')
            ->where('locales.1.native', 'English')
            ->has('translations')
        );
    }

    public function test_translations_payload_matches_active_locale(): void
    {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'en')
                ->where('translations.Guardar', 'Save')
            );
    }

    public function test_spanish_payload_does_not_need_entries(): void
    {
        // WHY: en español la clave ES el texto (plan.md §5). El catálogo llega casi vacío
        // a propósito; si algún día se llena, es señal de que se coló una clave semántica.
        $this->withHeader('Accept-Language', 'es')->get('/')->assertInertia(fn ($page) => $page
            ->where('locale', 'es')
            ->missing('translations.Guardar')
        );
    }
}
