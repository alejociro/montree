<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PaymentException extends \Exception implements HttpExceptionInterface
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

    public static function gatewayNotConfigured(): self
    {
        return new self(
            'PAYMENT_GATEWAY_NOT_CONFIGURED',
            __('Los pagos en línea no están configurados. Contactá a la agencia.'),
            503,
        );
    }

    public static function sessionRejected(string $message): self
    {
        return new self('PAYMENT_SESSION_REJECTED', $message !== '' ? $message : __('No pudimos iniciar el pago. Intentá de nuevo.'), 422);
    }

    public static function gatewayUnavailable(): self
    {
        return new self(
            'PAYMENT_GATEWAY_UNAVAILABLE',
            __('La pasarela de pagos no respondió. Intentá de nuevo en unos minutos.'),
            503,
        );
    }

    public static function notQueryable(): self
    {
        return new self('PAYMENT_NOT_QUERYABLE', __('Este pago no se puede consultar en la pasarela.'), 422);
    }

    public static function nothingDue(): self
    {
        return new self('PAYMENT_NOTHING_DUE', __('Esta reserva no tiene saldo pendiente.'), 422);
    }
}
