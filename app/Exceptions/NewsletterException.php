<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class NewsletterException extends \Exception implements HttpExceptionInterface
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

    public static function alreadySubscribed(): self
    {
        return new self('NEWSLETTER_ALREADY_SUBSCRIBED', 'Este email ya está suscrito.', 409);
    }

    public static function invalidToken(): self
    {
        return new self('NEWSLETTER_INVALID_TOKEN', 'El token de baja no es válido.', 404);
    }

    public static function noRecipients(): self
    {
        return new self('NEWSLETTER_NO_RECIPIENTS', 'No hay suscriptores activos.', 422);
    }
}
