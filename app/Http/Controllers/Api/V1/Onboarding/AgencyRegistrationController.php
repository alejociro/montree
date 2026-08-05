<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Actions\Onboarding\RegisterAgencyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterAgencyRequest;
use App\Http\Resources\Onboarding\AgencyRegistrationResource;
use Illuminate\Http\JsonResponse;

final class AgencyRegistrationController extends Controller
{
    public function store(RegisterAgencyRequest $request, RegisterAgencyAction $register): JsonResponse
    {
        $tenant = $register->handle($request->agencyData());

        return AgencyRegistrationResource::make($tenant)
            ->response()
            ->setStatusCode(201);
    }
}
