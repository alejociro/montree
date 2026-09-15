<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payment\ResolvePaymentAction;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

/**
 * Consulta un pago en la pasarela fuera del ciclo de la request. Lo usan la
 * notificación de PlacetoPay y el barrido de `payment:check`; el retorno del
 * comprador sigue resolviendo en línea porque necesita mostrar el resultado.
 *
 * `NotTenantAware` porque el tenant sale del propio pago: `payment:check`
 * despacha sin tenant activo y la cola tenant-aware descartaría el job.
 */
final class ResolvePaymentJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(private int $paymentId) {}

    public function handle(ResolvePaymentAction $resolvePayment): void
    {
        $payment = Payment::query()
            ->withoutGlobalScope('tenant')
            ->with('tenant.configuration')
            ->find($this->paymentId);

        if ($payment === null) {
            return;
        }
        $previousTenant = Tenant::current();

        try {
            $payment->tenant->makeCurrent();

            $resolvePayment->handle($payment);
        } finally {
            $previousTenant === null ? Tenant::forgetCurrent() : $previousTenant->makeCurrent();
        }
    }

    /**
     * Dos workers consultando el mismo pago solo duplican llamadas a la pasarela:
     * el que llega segundo no aporta nada y se descarta.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->paymentId))->dontRelease()];
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('No se pudo resolver el pago', [
            'payment_id' => $this->paymentId,
            'message' => $exception->getMessage(),
        ]);
    }
}
