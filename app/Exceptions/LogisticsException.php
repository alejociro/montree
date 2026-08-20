<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class LogisticsException extends \Exception implements HttpExceptionInterface
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

    public static function inUse(string $resource, int $count): self
    {
        return new self(
            'RESOURCE_IN_USE',
            trans_choice(
                '{1}No se puede eliminar: :resource en uso por :count salida.|[2,*]No se puede eliminar: :resource en uso por :count salidas.',
                $count,
                ['resource' => $resource, 'count' => $count],
            ),
            409,
        );
    }
}
