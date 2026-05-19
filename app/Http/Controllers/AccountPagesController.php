<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function review(Request $request, string $bookingNumber): Response
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with('tour:id,name,slug')
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        return Inertia::render('Account/Bookings/Review', [
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'tour_name' => $booking->tour->name,
                'tour_slug' => $booking->tour->slug,
                'has_review' => $booking->review()->exists(),
            ],
        ]);
    }
}
