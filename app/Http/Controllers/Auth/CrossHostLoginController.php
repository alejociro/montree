<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CrossHostLoginHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class CrossHostLoginController extends Controller
{
    public function __construct(private CrossHostLoginHandoff $handoff) {}

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $payload = $this->handoff->consume($token);

        if ($payload === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('El enlace de acceso expiró o ya fue usado. Inicia sesión de nuevo.'),
            ]);
        }

        $user = User::find($payload['user_id']);

        if ($user === null) {
            return redirect()->route('login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // WHY: redirect_to is always set internally at issuance; guard against open
        // redirect just in case by forcing a relative path.
        $target = Str::startsWith($payload['redirect_to'], '/') ? $payload['redirect_to'] : '/';

        return redirect()->to($target);
    }
}
