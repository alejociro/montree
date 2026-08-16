<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\CheckSubdomainAvailabilityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CheckSubdomainRequest;
use Illuminate\Http\JsonResponse;

/**
 * WHY: kept apart from AgencyOnboardingController because its consumer is the
 * debounced typeahead in the signup form, not a navigation. It is the only
 * onboarding handler that answers JSON instead of an Inertia page or a redirect.
 */
final class SubdomainAvailabilityController extends Controller
{
    public function __invoke(CheckSubdomainRequest $request, CheckSubdomainAvailabilityAction $check): JsonResponse
    {
        return new JsonResponse($check->handle($request->slug()));
    }
}
