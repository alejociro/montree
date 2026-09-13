<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * WHY: el checkout de invitado ya no abre sesión cuando el correo pertenece a una
 * cuenta que existía — escribir un correo no prueba poseerlo. La reserva se crea a
 * nombre del dueño y el acceso sale por este enlace de un solo uso, que es lo único
 * que sí exige tener la bandeja.
 *
 * La URL llega ya absoluta desde el request: la notificación se encola y en el worker
 * `url()` resolvería el host de la plataforma, no el subdominio de la agencia — y la
 * sesión que crea el handoff es host-only, así que el host equivocado no sirve de nada.
 *
 * No va por `database`: el token es una credencial y la bandeja de notificaciones solo
 * se ve estando dentro.
 */
final class BookingAccessLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $accessUrl,
        public readonly string $bookingNumber,
        public readonly string $tourName,
        public readonly int $minutesValid,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Completa tu reserva — :tour', ['tour' => $this->tourName]))
            ->line(__('Ya tienes una cuenta con este correo, así que la reserva quedó a tu nombre.'))
            ->line(__('Número: :number', ['number' => $this->bookingNumber]))
            ->line(__('Tour: :tour', ['tour' => $this->tourName]))
            ->action(__('Entrar y completar el pago'), $this->accessUrl)
            ->line(__('El enlace vence en :minutes minutos y solo puede usarse una vez.', ['minutes' => $this->minutesValid]))
            ->line(__('Si no fuiste tú quien intentó reservar, ignora este correo: sin abrir el enlace, nadie entra a tu cuenta.'));
    }
}
