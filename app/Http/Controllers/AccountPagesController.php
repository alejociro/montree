<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class AccountPagesController extends Controller
{
    public function profile(): Response
    {
        return Inertia::render('Account/Profile');
    }

    public function bookings(): Response
    {
        return Inertia::render('Account/Bookings');
    }

    public function favorites(): Response
    {
        return Inertia::render('Account/Favorites');
    }
}
