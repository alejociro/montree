<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Tenant\UpdateTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

final class TenantController extends Controller
{
    public function __construct(private UpdateTenantAction $action) {}

    public function update(UpdateTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, 'No tenant for this host.');

        $updated = $this->action->handle($tenant, $request->validated());

        return new JsonResponse([
            'data' => [
                'tenant' => (new TenantResource($updated))->resolve(),
            ],
        ]);
    }
}
