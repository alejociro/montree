<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ReviewPagesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Reviews/Index');
    }
}
