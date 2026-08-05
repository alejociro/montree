<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\ClaimAgencyAccessAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ClaimAgencyController extends Controller
{
    public function __invoke(Request $request, ClaimAgencyAccessAction $claim): RedirectResponse
    {
        $user = $claim->handle((string) $request->query('nonce'), Tenant::current());

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('El enlace de acceso expiró o ya fue usado. Iniciá sesión de nuevo.'),
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to('/admin/dashboard');
    }
}
