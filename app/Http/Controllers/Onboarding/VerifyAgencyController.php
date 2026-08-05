<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\ActivateAgencyAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VerifyAgencyController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, User $user, ActivateAgencyAction $activate): RedirectResponse
    {
        $claimUrl = $activate->handle($tenant, $user, $request);

        return redirect()->away($claimUrl);
    }
}
