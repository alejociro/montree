<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payment\ResolvePaymentAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vuelta del comprador desde PlacetoPay. La firma de la URL es la credencial:
 * la ruta es pública porque la cookie de sesión puede no viajar de vuelta, y
 * cubre el host, así que el enlace de una agencia no sirve en otra.
 */
final class PaymentReturnController extends Controller
{
    public function __construct(private ResolvePaymentAction $resolvePayment) {}

    public function __invoke(int $payment): RedirectResponse
    {
        $found = Payment::query()->whereKey($payment)->with('booking')->first();

        if ($found === null) {
            throw new NotFoundHttpException(__('No encontramos el pago indicado.'));
        }

        $found = $this->resolvePayment->handle($found);

        return redirect()
            ->route('booking.show', $found->booking->booking_number)
            ->with(...$this->flash($found));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function flash(Payment $payment): array
    {
        return match ($payment->status) {
            PaymentStatus::Completed => ['success', __('¡Pago aprobado! Tu reserva quedó confirmada.')],
            PaymentStatus::Failed => ['error', $payment->status_message ?: __('El pago fue rechazado. Podés intentarlo de nuevo.')],
            default => ['success', __('Estamos confirmando tu pago con el banco. Te avisamos apenas se resuelva.')],
        };
    }
}
