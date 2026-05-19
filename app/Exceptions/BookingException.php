<?php

declare(strict_types=1);

namespace App\Exceptions;

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
        return new self('TOUR_DATE_NOT_AVAILABLE', 'La fecha seleccionada ya no está disponible.', 422);
    }

    public static function insufficientCapacity(int $available): self
    {
        return new self('INSUFFICIENT_CAPACITY', "Solo quedan {$available} cupos en esta fecha.", 409);
    }

    public static function bookingWindowClosed(): self
    {
        return new self('BOOKING_WINDOW_CLOSED', 'La fecha ya no admite reservas.', 422);
    }

    public static function notFound(): self
    {
        return new self('BOOKING_NOT_FOUND', 'No encontramos la reserva indicada.', 404);
    }
}
