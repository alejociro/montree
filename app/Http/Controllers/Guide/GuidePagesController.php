<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guide;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class GuidePagesController extends Controller
{
    public function schedule(): Response
    {
        return Inertia::render('Guide/Schedule');
    }
}
