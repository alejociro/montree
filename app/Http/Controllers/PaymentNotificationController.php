<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Payment\NotificationRequest;
use App\Jobs\ResolvePaymentJob;
use App\Models\Payment;
use App\Services\PlaceToPay\NotificationSignature;
use Illuminate\Http\JsonResponse;

/**
 * Notificación servidor a servidor de PlacetoPay. No se cree lo que llega: se
 * valida la firma y se encola una consulta a la pasarela, que es la fuente de
 * verdad del estado del pago.
 *
 * El pago se busca sin scope de tenant: la URL se configura en el panel del
 * comercio y no siempre cae en el subdominio de la agencia. El tenant sale del
 * pago, y la firma se valida contra la llave de ese tenant.
 */
final class PaymentNotificationController extends Controller
{
    public function __construct(private NotificationSignature $signature) {}

    public function __invoke(NotificationRequest $request): JsonResponse
    {
        $notification = $request->notification();
        $payment = Payment::query()
            ->withoutGlobalScope('tenant')
            ->where('request_id', $notification['requestId'])
            ->where('reference', (string) $request->input('reference'))
            ->first();

        if ($payment === null || ! $this->signature->matches($payment->tenant, $notification)) {
            logger()->warning('PlacetoPay NOTIFICATION rechazada', [
                'request_id' => $notification['requestId'],
                'reference' => $request->input('reference'),
                'payment_found' => $payment !== null,
            ]);

            return response()->json(['message' => 'Invalid notification.'], 422);
        }

        ResolvePaymentJob::dispatch($payment->id);

        return response()->json(['message' => 'Notification queued.']);
    }
}
