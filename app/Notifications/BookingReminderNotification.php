<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BookingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $bookingNumber,
        public readonly string $tourName,
        public readonly string $startsAt,
        public readonly ?string $meetingPoint,
    ) {}

    public static function fromBooking(Booking $booking): self
    {
        $booking->loadMissing('tour', 'tourDate');

        return new self(
            $booking->id,
            $booking->booking_number,
            $booking->tour->name,
            $booking->tourDate->starts_at->toIso8601String(),
            $booking->tour->meeting_point,
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
        $mail = (new MailMessage)
            ->subject("Recordatorio: tu tour {$this->tourName} es mañana")
            ->line("Tu tour \"{$this->tourName}\" es mañana.")
            ->line("Fecha: {$this->startsAt}");

        if ($this->meetingPoint) {
            $mail->line("Punto de encuentro: {$this->meetingPoint}");
        }

        return $mail
            ->action('Ver reserva', url("/bookings/{$this->bookingNumber}"))
            ->line('¡Prepará todo para disfrutar!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'booking_reminder',
            'booking_id' => $this->bookingId,
            'booking_number' => $this->bookingNumber,
            'tour_name' => $this->tourName,
            'starts_at' => $this->startsAt,
            'meeting_point' => $this->meetingPoint,
        ];
    }
}
