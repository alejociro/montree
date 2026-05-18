<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class NewsletterPagesController extends Controller
{
    public function unsubscribe(string $token): Response
    {
        return Inertia::render('Newsletter/Unsubscribe', ['token' => $token]);
    }

    public function admin(): Response
    {
        return Inertia::render('Admin/Newsletter/Index');
    }
}
