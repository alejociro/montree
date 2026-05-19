<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminTenantPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Tenant/Index');
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('SuperAdmin/Tenant/Detail', [
            'tenantId' => $tenant->id,
        ]);
    }
}
