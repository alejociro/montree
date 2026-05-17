<?php

declare(strict_types=1);

namespace App\Http\Controllers\Errors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class TenantNotFoundController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Errors/TenantNotFound')
            ->toResponse($request)
            ->setStatusCode(404);
    }
}
