<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Payment;

/**
 * Resultado de reconsultar una transacción en la pasarela: el pago como quedó y
 * si ya estaba resuelto antes de preguntar, que es lo único que distingue los
 * dos mensajes del panel.
 */
final readonly class TransactionQueryResult
{
    public function __construct(
        public Payment $payment,
        public bool $wasAlreadyResolved,
    ) {}
}
