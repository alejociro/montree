<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSchedule;
use Inertia\Inertia;
use Inertia\Response;

final class CommissionSchedulePageController extends Controller
{
    public function index(): Response
    {
        $schedule = CommissionSchedule::global();

        return Inertia::render('SuperAdmin/Commission/Index', [
            'schedule' => [
                'currency' => $schedule->currency,
                'tiers' => $schedule->tiers,
                'max_charge' => $schedule->max_charge,
            ],
        ]);
    }
}
