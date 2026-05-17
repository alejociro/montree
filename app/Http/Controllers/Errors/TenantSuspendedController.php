<?php

declare(strict_types=1);

namespace App\Http\Controllers\Errors;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class TenantSuspendedController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): Response
    {
        return Inertia::render('Errors/TenantSuspended', [
            'tenantName' => $tenant->name,
            'contactEmail' => $tenant->contact_email,
        ])
            ->toResponse($request)
            ->setStatusCode(503);
    }
}
