<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BookingException extends \Exception implements HttpExceptionInterface
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->statusCode);
    }

    public static function dateNotAvailable(): self
    {
        return new self('TOUR_DATE_NOT_AVAILABLE', __('La fecha seleccionada ya no está disponible.'), 422);
    }

    public static function insufficientCapacity(int $available): self
    {
        return new self('INSUFFICIENT_CAPACITY', "Solo quedan {$available} cupos en esta fecha.", 409);
    }

    public static function bookingWindowClosed(): self
    {
        return new self('BOOKING_WINDOW_CLOSED', __('La fecha ya no admite reservas.'), 422);
    }

    public static function notFound(): self
    {
        return new self('BOOKING_NOT_FOUND', __('No encontramos la reserva indicada.'), 404);
    }

    public static function travelersComplete(int $travelersCount): self
    {
        return new self(
            'BOOKING_TRAVELERS_COMPLETE',
            __('La reserva ya tiene sus :count pasajeros cargados.', ['count' => $travelersCount]),
            409,
        );
    }

    public static function paymentsLocked(): self
    {
        return new self('BOOKING_PAYMENTS_LOCKED', __('No es posible registrar pagos sobre una reserva cancelada, expirada o reembolsada.'), 409);
    }

    public static function travelersLocked(): self
    {
        return new self('BOOKING_TRAVELERS_LOCKED', __('No es posible editar los viajeros de una reserva cancelada, expirada o reembolsada.'), 409);
    }

    /**
     * D10: la planilla se congela para el titular a las
     * `montree.passengers.traveler_edit_cutoff_hours` de la salida. El panel de
     * la agencia no pasa por aca.
     */
    public static function travelerEditWindowClosed(CarbonInterface $deadline): self
    {
        return new self(
            'BOOKING_TRAVELER_EDIT_WINDOW_CLOSED',
            __('Los datos de los viajeros se podían editar hasta el :deadline. Para un cambio de última hora, contacta a la agencia.', [
                'deadline' => $deadline->format('d/m/Y H:i'),
            ]),
            409,
        );
    }
}
