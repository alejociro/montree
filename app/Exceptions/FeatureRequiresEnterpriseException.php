<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class FeatureRequiresEnterpriseException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(private string $feature)
    {
        parent::__construct("Feature [{$this->feature}] requires the enterprise plan.");
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

    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error_code' => 'FEATURE_REQUIRES_ENTERPRISE',
            'feature' => $this->feature,
        ], 403);
    }
}
