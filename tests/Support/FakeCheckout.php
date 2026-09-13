<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\CheckoutClientFactory;
use App\Exceptions\PaymentException;
use App\Models\Tenant;
use App\Services\PlaceToPay\CheckoutCredentials;
use Dnetix\Redirection\PlacetoPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Doble de la pasarela para la suite: encola respuestas HTTP y construye el
 * cliente real de la librería sobre un handler de Guzzle simulado.
 *
 * WHY: se simula el transporte y no el cliente. Así los tests ejercitan el
 * armado del payload y el parseo de la respuesta de `dnetix/redirection`, que es
 * justo donde se rompen estas integraciones.
 */
final class FakeCheckout implements CheckoutClientFactory
{
    /** Fecha que reporta el autorizador en las respuestas simuladas. */
    public const TRANSACTION_DATE = '2026-08-26T10:00:00-05:00';

    private MockHandler $handler;

    /** @var array<int, Request> */
    public array $requests = [];

    public function __construct()
    {
        $this->handler = new MockHandler;
    }

    public static function fake(): self
    {
        $fake = new self;

        app()->instance(CheckoutClientFactory::class, $fake);

        return $fake;
    }

    public function for(Tenant $tenant): PlacetoPay
    {
        // Se resuelven las credenciales de verdad: el doble solo sustituye el
        // transporte, no la decision de con que comercio se cobra.
        $credentials = CheckoutCredentials::resolve($tenant);

        if ($credentials === null) {
            throw PaymentException::gatewayNotConfigured();
        }

        $stack = HandlerStack::create($this->handler);
        $stack->push(function (callable $next) {
            return function (Request $request, array $options) use ($next) {
                $this->requests[] = $request;

                return $next($request, $options);
            };
        });

        return new PlacetoPay([
            'login' => $credentials->login,
            'tranKey' => $credentials->tranKey,
            'baseUrl' => $credentials->url,
            'client' => new Client(['handler' => $stack]),
        ]);
    }

    public function sessionCreated(int $requestId = 12345, string $processUrl = 'https://checkout.test/session/12345/abc'): self
    {
        return $this->push([
            'status' => $this->status('OK', 'PC', 'La petición se ha procesado correctamente'),
            'requestId' => $requestId,
            'processUrl' => $processUrl,
        ]);
    }

    public function sessionRejected(string $message = 'Credenciales inválidas'): self
    {
        return $this->push([
            'status' => $this->status('FAILED', 'PF', $message),
        ]);
    }

    /**
     * Se encola una respuesta por intento: `retry()` consume la cola completa.
     */
    public function serviceDown(?int $attempts = null): self
    {
        $attempts ??= (int) config('placetopay.retry.attempts');

        for ($i = 0; $i < max(1, $attempts); $i++) {
            $this->handler->append(new Response(500, [], 'gateway exploded'));
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    public function queryApproved(string $amount, int $requestId = 12345, array $transaction = []): self
    {
        return $this->pushQuery('APPROVED', $requestId, array_merge([
            'reference' => 'MTR-1',
            'internalReference' => '1122334455',
            'paymentMethod' => 'visa',
            'paymentMethodName' => 'Visa',
            'issuerName' => 'BANCOLOMBIA',
            'franchise' => 'CR_VS',
            'authorization' => '999999',
            'receipt' => '170821222',
            'status' => $this->status('APPROVED', '00', 'Aprobada'),
            'amount' => $this->amount($amount),
            'processorFields' => [
                ['keyword' => 'id', 'value' => '000000', 'displayOn' => 'none'],
                ['keyword' => 'b24', 'value' => '999999', 'displayOn' => 'none'],
            ],
        ], $transaction));
    }

    public function queryRejected(string $message = 'Rechazada por el banco', int $requestId = 12345, string $sessionStatus = 'REJECTED'): self
    {
        return $this->pushQuery($sessionStatus, $requestId, [
            'reference' => 'MTR-1',
            'internalReference' => '1122334455',
            'status' => $this->status('REJECTED', '05', $message),
            'amount' => $this->amount('1.00'),
        ]);
    }

    public function queryPending(int $requestId = 12345): self
    {
        return $this->push([
            'requestId' => (string) $requestId,
            'status' => $this->status('PENDING', 'PC', 'La petición se encuentra pendiente'),
            'request' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function amount(string $total): array
    {
        $base = ['currency' => 'COP', 'total' => (float) $total];

        return ['from' => $base, 'to' => $base, 'factor' => 1];
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function pushQuery(string $status, int $requestId, array $transaction): self
    {
        return $this->push([
            'requestId' => (string) $requestId,
            'status' => $this->status($status, '00', 'Resuelta'),
            'request' => [],
            'payment' => [$transaction],
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function push(array $body): self
    {
        $this->handler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body)));

        return $this;
    }

    /**
     * @return array<string, string>
     */
    private function status(string $status, string $reason, string $message): array
    {
        return [
            'status' => $status,
            'reason' => $reason,
            'message' => $message,
            'date' => self::TRANSACTION_DATE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastPayload(): array
    {
        $request = end($this->requests);

        return $request === false ? [] : (array) json_decode((string) $request->getBody(), true);
    }
}
