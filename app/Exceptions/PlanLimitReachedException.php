<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PlanLimitReachedException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(private string $errorCode, private string $userMessage)
    {
        parent::__construct($userMessage);
    }

    public static function tours(int $max): self
    {
        return new self(
            'PLAN_LIMIT_TOURS_REACHED',
            "Your plan allows up to {$max} tours. Upgrade to create more.",
        );
    }

    public function getStatusCode(): int
    {
        return 403;
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
        ], 403);
    }
}
