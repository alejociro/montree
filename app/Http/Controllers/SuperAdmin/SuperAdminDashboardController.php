<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard');
    }
}
