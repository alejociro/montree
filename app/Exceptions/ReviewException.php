<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ReviewException extends \Exception implements HttpExceptionInterface
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

    public static function bookingNotCompleted(): self
    {
        return new self('BOOKING_NOT_COMPLETED', 'Solo se puede reseñar después de completar el tour.', 403);
    }

    public static function alreadyReviewed(): self
    {
        return new self('REVIEW_ALREADY_EXISTS', 'Ya enviaste una reseña para esta reserva.', 409);
    }

    public static function alreadyResponded(): self
    {
        return new self('REVIEW_ALREADY_RESPONDED', 'Esta reseña ya tiene respuesta.', 409);
    }
}
