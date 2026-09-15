<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ResolvePaymentJob;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Cierra los pagos que la pasarela ya resolvió pero nadie vino a contarnos: el
 * comprador cerró el navegador y la notificación no llegó o falló.
 *
 * Es la red de seguridad del retorno y de la notificación, no su reemplazo.
 */
final class CheckPendingPaymentsCommand extends Command
{
    protected $signature = 'payment:check
        {--date-from= : Consultar pagos creados desde esta fecha (Y-m-d)}
        {--date-to= : Consultar pagos creados hasta esta fecha (Y-m-d)}';

    protected $description = 'Encola la consulta en PlacetoPay de los pagos que quedaron sin resolver';

    public function handle(): int
    {
        [$from, $to] = $this->window();

        /**
         * Sin scope de tenant: el comando corre fuera de una request y tiene
         * que ver los pagos de todas las agencias. Cada job activa el suyo.
         */
        $payments = Payment::query()
            ->withoutGlobalScope('tenant')
            ->unresolved()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get(['id', 'reference']);

        if ($payments->isEmpty()) {
            $this->info('No hay pagos pendientes de resolver.');

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($payments as $payment) {
            $queued += $this->queue($payment) ? 1 : 0;
        }

        $this->info(sprintf('%d/%d pagos encolados.', $queued, $payments->count()));

        return self::SUCCESS;
    }

    private function queue(Payment $payment): bool
    {
        try {
            ResolvePaymentJob::dispatch($payment->id);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(sprintf('[%s] %s', $payment->reference, $exception->getMessage()));

            return false;
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(): array
    {
        $from = $this->option('date-from') !== null
            ? CarbonImmutable::parse((string) $this->option('date-from'))->startOfDay()
            : now()->subHours((int) config('placetopay.check.lookback_hours'));

        /**
         * El margen evita pisarle la sesión a alguien que todavía está pagando:
         * consultar una sesión viva no la rompe, pero tampoco aporta nada.
         */
        $to = $this->option('date-to') !== null
            ? CarbonImmutable::parse((string) $this->option('date-to'))->endOfDay()
            : now()->subMinutes((int) config('placetopay.check.settle_margin_minutes'));

        return [CarbonImmutable::instance($from), CarbonImmutable::instance($to)];
    }
}
