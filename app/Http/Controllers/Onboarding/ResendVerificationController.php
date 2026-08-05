<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\ResendAgencyVerificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ResendVerificationRequest;
use Illuminate\Http\RedirectResponse;

final class ResendVerificationController extends Controller
{
    public function __invoke(ResendVerificationRequest $request, ResendAgencyVerificationAction $resend): RedirectResponse
    {
        $resend->handle($request->email());

        return back()->with('status', __('Si la cuenta existe, te reenviamos el email de verificación.'));
    }
}
