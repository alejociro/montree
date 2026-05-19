<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PromotionInvalidException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        private string $errorCode,
        private string $userMessage,
        private int $statusCode = 422,
    ) {
        parent::__construct($userMessage);
    }

    public static function notFound(): self
    {
        return new self(
            'PROMOTION_NOT_FOUND',
            'El código ingresado no existe.',
            404,
        );
    }

    public static function expired(): self
    {
        return new self(
            'PROMOTION_EXPIRED',
            'Este código promocional expiró.',
        );
    }

    public static function inactive(): self
    {
        return new self(
            'PROMOTION_INACTIVE',
            'Este código promocional está inactivo.',
        );
    }

    public static function exhausted(): self
    {
        return new self(
            'PROMOTION_EXHAUSTED',
            'Este código alcanzó su máximo de usos.',
        );
    }

    public static function minAmountNotMet(string $minAmount): self
    {
        return new self(
            'PROMOTION_MIN_AMOUNT_NOT_MET',
            "Este código requiere un monto mínimo de {$minAmount}.",
        );
    }

    public static function tourNotApplicable(): self
    {
        return new self(
            'PROMOTION_TOUR_NOT_APPLICABLE',
            'Este código no aplica para este tour.',
        );
    }

    public static function userLimitReached(): self
    {
        return new self(
            'PROMOTION_USER_LIMIT_REACHED',
            'Ya usaste este código el máximo de veces permitido.',
        );
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

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->userMessage,
            'error_code' => $this->errorCode,
        ], $this->statusCode);
    }
}
