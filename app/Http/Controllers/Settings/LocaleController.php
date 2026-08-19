<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateLocaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

final class LocaleController extends Controller
{
    public function __construct(private readonly UpdateLocaleAction $updateLocale) {}

    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');

        $this->updateLocale->handle($request->user(), $locale);

        // WHY: la cookie se escribe siempre, tambien para el usuario autenticado, para que
        // ambas fuentes queden alineadas y cerrar sesion no lo devuelva al idioma anterior.
        return back()->withCookie(Cookie::make('locale', $locale, 60 * 24 * 365, sameSite: 'lax'));
    }
}
