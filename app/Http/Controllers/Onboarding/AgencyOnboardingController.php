<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\ActivateAgencyAction;
use App\Actions\Onboarding\RegisterAgencyAction;
use App\Actions\Onboarding\ResendAgencyVerificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterAgencyRequest;
use App\Http\Requests\Onboarding\ResendVerificationRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AgencyOnboardingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Onboarding/CreateAgency');
    }

    public function store(RegisterAgencyRequest $request, RegisterAgencyAction $register): RedirectResponse
    {
        $data = $request->agencyData();

        $register->handle($data);

        // WHY: kept in the session instead of the query string. A forgeable
        // ?email= let anyone render this branded page around an address they
        // chose, and put every signup address into browser history, access logs
        // and the Referer header. Not flashed, so refreshing while waiting for
        // the email keeps the resend button alive.
        $request->session()->put('onboarding.pending', [
            'email' => $data['email'],
            'agency_name' => $data['agency_name'],
        ]);

        return redirect()->route('onboarding.check-email');
    }

    public function checkEmail(Request $request): Response
    {
        $pending = $request->session()->get('onboarding.pending');

        return Inertia::render('Onboarding/CheckEmail', [
            'email' => is_array($pending) ? ($pending['email'] ?? null) : null,
            'agencyName' => is_array($pending) ? ($pending['agency_name'] ?? null) : null,
        ]);
    }

    public function resendVerification(ResendVerificationRequest $request, ResendAgencyVerificationAction $resend): RedirectResponse
    {
        $resend->handle($request->email());

        return back()->with('status', __('Si la cuenta existe, te reenviamos el email de verificación.'));
    }

    public function verify(Request $request, Tenant $tenant, User $user, ActivateAgencyAction $activate): RedirectResponse
    {
        return redirect()->away($activate->handle($tenant, $user, $request));
    }
}
