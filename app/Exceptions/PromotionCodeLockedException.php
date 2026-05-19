<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PromotionCodeLockedException extends RuntimeException implements HttpExceptionInterface
{
    private string $errorCode = 'PROMOTION_CODE_LOCKED';

    public function __construct()
    {
        parent::__construct('No se puede modificar el código de una promoción que ya fue usada.');
    }

    public function getStatusCode(): int
    {
        return 422;
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
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 422);
    }
}
