<?php

declare(strict_types=1);

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale_and_gets_cookie(): void
    {
        $response = $this->from('/')->patch(route('locale.update'), ['locale' => 'en']);

        $response->assertRedirect('/');
        $response->assertSessionHasNoErrors();
        // El tercer argumento apaga la desencriptacion: `locale` esta en
        // `encryptCookies(except: ...)` porque el frontend la lee tal cual.
        $response->assertCookie('locale', 'en', false);
    }

    public function test_authenticated_user_locale_is_persisted(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->from('/')
            ->patch(route('locale.update'), ['locale' => 'en'])
            ->assertSessionHasNoErrors();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_unsupported_locale_is_rejected_with_422(): void
    {
        $this->withHeader('Accept-Language', 'es')
            ->patchJson(route('locale.update'), ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');

        $this->assertSame('es', app()->getLocale());
    }

    public function test_user_preference_wins_over_cookie(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withUnencryptedCookie('locale', 'es')
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }

    public function test_accept_language_header_is_used_when_no_cookie_and_no_user(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }

    public function test_falls_back_to_default_locale(): void
    {
        // WHY: el cliente de pruebas manda `Accept-Language: en-us,en;q=0.5` por defecto.
        // Para ejercitar el ultimo escalon de la cadena hay que pedir un idioma que no
        // este en el catalogo.
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('locale', 'es'));
    }
}
