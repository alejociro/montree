<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class TourDateException extends \Exception implements HttpExceptionInterface
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 409,
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

    public static function hasBookings(): self
    {
        return new self('TOUR_DATE_HAS_BOOKINGS', __('La salida tiene reservas asociadas y no puede modificarse así.'), 409);
    }

    public static function alreadyCancelled(): self
    {
        return new self('TOUR_DATE_ALREADY_CANCELLED', __('La salida ya está cancelada.'), 409);
    }

    public static function cancelled(): self
    {
        return new self('TOUR_DATE_CANCELLED', __('No es posible editar una salida cancelada.'), 409);
    }
}
