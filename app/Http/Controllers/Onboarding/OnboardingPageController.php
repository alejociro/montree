<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OnboardingPageController extends Controller
{
    public function start(): Response
    {
        return Inertia::render('Onboarding/CreateAgency');
    }

    public function checkEmail(Request $request): Response
    {
        return Inertia::render('Onboarding/CheckEmail', [
            'email' => $request->string('email')->toString() ?: null,
            'agencyName' => $request->string('agency')->toString() ?: null,
        ]);
    }
}
