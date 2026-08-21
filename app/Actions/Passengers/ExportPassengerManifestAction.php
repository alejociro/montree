<?php

declare(strict_types=1);

namespace App\Actions\Passengers;

use App\Models\BookingTraveler;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV de la planilla, para las dos zonas.
 *
 * WHY: sin el permiso médico las columnas `EPS` y `Observaciones` no se emiten
 * —ni el encabezado ni la celda—, en vez de emitirse vacías: una columna vacía
 * invita a preguntar qué falta (D7). El BOM va delante porque Excel en Windows
 * lee el archivo como Latin-1 sin él y parte cada tilde.
 */
final class ExportPassengerManifestAction
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  Collection<int, BookingTraveler>  $rows
     */
    public function handle(Collection $rows, string $slug, bool $canViewMedical): StreamedResponse
    {
        $filename = sprintf('pasajeros-%s-%s.csv', $slug, now()->toDateString());

        return new StreamedResponse(function () use ($rows, $canViewMedical): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, self::BOM);
            fputcsv($handle, $this->headers($canViewMedical));

            foreach ($rows as $passenger) {
                fputcsv($handle, $this->row($passenger, $canViewMedical));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function headers(bool $canViewMedical): array
    {
        $medical = $canViewMedical ? [__('EPS'), __('Observaciones')] : [];

        return [
            __('Nombre completo'),
            __('Tipo de documento'),
            __('Documento'),
            __('Email'),
            __('Teléfono'),
            __('Contacto de emergencia'),
            __('Parentesco'),
            __('Teléfono de emergencia'),
            ...$medical,
            __('Salida'),
            __('Reserva'),
            __('Valor'),
            __('Abonado'),
            __('Saldo'),
            __('Estado'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function row(BookingTraveler $passenger, bool $canViewMedical): array
    {
        $booking = $passenger->booking;
        $payment = $booking?->passengerShare();
        $medical = $canViewMedical
            ? [$this->epsLabel($passenger), (string) $passenger->medical_notes]
            : [];

        return [
            (string) $passenger->full_name,
            (string) $passenger->document_type?->label(),
            (string) $passenger->document_number,
            (string) $passenger->email,
            (string) $passenger->phone,
            (string) $passenger->emergency_contact_name,
            (string) $passenger->emergency_contact_relationship,
            (string) $passenger->emergency_contact_phone,
            ...$medical,
            (string) $booking?->tourDate?->starts_at?->format('Y-m-d H:i'),
            (string) $booking?->booking_number,
            (string) ($payment['share_amount'] ?? ''),
            (string) ($payment['paid_amount'] ?? ''),
            (string) ($payment['due_amount'] ?? ''),
            $this->statusLabel($payment['status'] ?? null),
        ];
    }

    private function epsLabel(BookingTraveler $passenger): string
    {
        if ($passenger->eps === null) {
            return '';
        }

        return $passenger->eps->requiresDetail() && $passenger->eps_other !== null
            ? $passenger->eps_other
            : $passenger->eps->label();
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'due' => __('Saldo pendiente'),
            'paid' => __('Pagado'),
            default => '',
        };
    }
}
