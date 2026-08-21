<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Regla 6 del handoff: cambió la parada de recogida de un tour con reservas
 * vivas. Va encolada porque un tour con cincuenta reservas son cincuenta
 * correos, y guardar el tour no puede esperar a que salgan.
 */
final class PickupPointChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $bookingNumber,
        public readonly string $tourName,
        public readonly string $startsAt,
        public readonly ?string $previousPickup,
        public readonly ?string $currentPickup,
    ) {}

    public static function fromBooking(Booking $booking, ?string $previousPickup, ?string $currentPickup): self
    {
        $booking->loadMissing('tour', 'tourDate');

        return new self(
            $booking->id,
            $booking->booking_number,
            $booking->tour->name,
            $booking->tourDate->starts_at->toIso8601String(),
            $previousPickup,
            $currentPickup,
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
            ->subject(__('Cambió el punto de recogida de :tour', ['tour' => $this->tourName]))
            ->line(__('El punto de recogida de tu tour ":tour" cambió.', ['tour' => $this->tourName]))
            ->line(__('Fecha: :date', ['date' => $this->startsAt]));

        if ($this->previousPickup !== null) {
            $mail->line(__('Antes: :place', ['place' => $this->previousPickup]));
        }

        $mail->line($this->currentPickup !== null
            ? __('Ahora: :place', ['place' => $this->currentPickup])
            : __('El tour ya no tiene un punto de recogida definido. La agencia te contactará.'));

        return $mail
            ->action(__('Ver reserva'), url("/bookings/{$this->bookingNumber}"))
            ->line(__('Revisa la hora y el lugar antes de salir.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'pickup_point_changed',
            'booking_id' => $this->bookingId,
            'booking_number' => $this->bookingNumber,
            'tour_name' => $this->tourName,
            'starts_at' => $this->startsAt,
            'previous_pickup' => $this->previousPickup,
            'current_pickup' => $this->currentPickup,
        ];
    }
}
