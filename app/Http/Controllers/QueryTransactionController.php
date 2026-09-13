<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payment\QueryTransactionAction;
use App\Data\TransactionQueryResult;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;

/**
 * Reconsulta manual de una transacción. No recibe input: la autorización va en
 * la ruta (`can:payments.query`) y la regla de negocio en la Action.
 */
final class QueryTransactionController extends Controller
{
    public function __construct(private QueryTransactionAction $queryTransaction) {}

    public function __invoke(Payment $payment): RedirectResponse
    {
        return back()->with('success', $this->message($this->queryTransaction->handle($payment)));
    }

    private function message(TransactionQueryResult $result): string
    {
        $status = $result->payment->status->label();

        return $result->wasAlreadyResolved
            ? __('El pago ya estaba resuelto como :status.', ['status' => $status])
            : __('La pasarela respondió: :status.', ['status' => $status]);
    }
}
