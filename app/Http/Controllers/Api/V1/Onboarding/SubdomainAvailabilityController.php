<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Actions\Onboarding\CheckSubdomainAvailabilityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CheckSubdomainRequest;
use Illuminate\Http\JsonResponse;

final class SubdomainAvailabilityController extends Controller
{
    public function __invoke(CheckSubdomainRequest $request, CheckSubdomainAvailabilityAction $check): JsonResponse
    {
        return new JsonResponse($check->handle($request->slug()));
    }
}
