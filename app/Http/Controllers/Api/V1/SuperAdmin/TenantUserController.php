<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\SuperAdmin\CreateTenantUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantUserRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TenantUserController extends Controller
{
    public function store(StoreTenantUserRequest $request, Tenant $tenant, CreateTenantUserAction $action): JsonResponse
    {
        $user = $action->handle($tenant, $request->validated());

        return new JsonResponse([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], Response::HTTP_CREATED);
    }
}
