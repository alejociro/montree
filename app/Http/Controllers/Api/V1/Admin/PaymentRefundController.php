<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payment\RefundPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

final class PaymentRefundController extends Controller
{
    public function __construct(private RefundPaymentAction $refund) {}

    public function __invoke(Request $request, Payment $payment): PaymentResource
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        return new PaymentResource($this->refund->handle($payment, $reason));
    }
}
