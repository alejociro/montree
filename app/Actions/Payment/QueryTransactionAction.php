<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Data\TransactionQueryResult;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use Dnetix\Redirection\Exceptions\PlacetoPayServiceException;

final class QueryTransactionAction
{
    public function __construct(private ResolvePaymentAction $resolve) {}

    public function handle(Payment $payment): TransactionQueryResult
    {
        if (! $payment->isQueryable()) {
            throw PaymentException::notQueryable();
        }

        $wasAlreadyResolved = $payment->isResolved();

        try {
            $resolved = $this->resolve->handle($payment);
        } catch (PlacetoPayServiceException $exception) {
            report($exception);

            throw PaymentException::gatewayUnavailable();
        }

        return new TransactionQueryResult($resolved, $wasAlreadyResolved);
    }
}
