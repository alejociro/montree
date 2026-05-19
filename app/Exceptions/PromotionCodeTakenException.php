<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PromotionCodeTakenException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Ya existe una promoción con este código.');
    }

    public function getStatusCode(): int
    {
        return 409;
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
            'error_code' => 'PROMOTION_CODE_TAKEN',
        ], 409);
    }
}
