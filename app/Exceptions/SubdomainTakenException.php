<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class SubdomainTakenException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct()
    {
        parent::__construct(__('Ese subdominio ya fue reclamado.'));
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
            'error_code' => 'SUBDOMAIN_TAKEN',
        ], 409);
    }
}
