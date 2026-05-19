<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $bookingNumber,
        public readonly string $tourName,
        public readonly string $startsAt,
        public readonly string $totalAmount,
        public readonly string $currency,
    ) {}

    public static function fromBooking(Booking $booking): self
    {
        $booking->loadMissing('tour', 'tourDate');

        return new self(
            $booking->id,
            $booking->booking_number,
            $booking->tour->name,
            $booking->tourDate->starts_at->toIso8601String(),
            $booking->total_amount,
            $booking->currency,
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reserva confirmada — {$this->tourName}")
            ->line('Tu reserva ha sido confirmada.')
            ->line("Número: {$this->bookingNumber}")
            ->line("Tour: {$this->tourName}")
            ->action('Ver detalles', url("/bookings/{$this->bookingNumber}"))
            ->line('¡Gracias por reservar con nosotros!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'booking_confirmed',
            'booking_id' => $this->bookingId,
            'booking_number' => $this->bookingNumber,
            'tour_name' => $this->tourName,
            'starts_at' => $this->startsAt,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
        ];
    }
}
