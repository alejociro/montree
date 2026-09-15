<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Campo contra el que busca el listado de transacciones.
 *
 * WHY: no hay búsqueda libre sobre todas las columnas. Quien concilia sabe qué
 * identificador tiene en la mano, y buscar por uno solo permite comparar por
 * igualdad —que usa los índices— en vez de un `LIKE %…%` sobre media tabla.
 * Es el mismo criterio que microsites, donde `Payment` no declara columnas
 * buscables justamente para no habilitar el barrido.
 */
enum TransactionSearchField: string
{
    case Reference = 'reference';
    case RequestId = 'request_id';
    case InternalReference = 'internal_reference';
    case Authorization = 'authorization';
    case Receipt = 'receipt';
    case BookingNumber = 'booking_number';
    case Payer = 'payer';

    public function label(): string
    {
        return match ($this) {
            self::Reference => __('Referencia'),
            self::RequestId => __('requestId (pasarela)'),
            self::InternalReference => __('Referencia interna'),
            self::Authorization => __('Autorización'),
            self::Receipt => __('Recibo'),
            self::BookingNumber => __('Número de reserva'),
            self::Payer => __('Nombre del pagador'),
        };
    }

    /**
     * El nombre del pagador es el único que no se compara por igualdad: nadie
     * recuerda cómo se escribió exacto. Los demás son identificadores que se
     * copian y pegan enteros.
     */
    public function isExact(): bool
    {
        return $this !== self::Payer;
    }

    /**
     * Un identificador exacto busca en toda la historia: si alguien pega un
     * `requestId`, quiere ese pago, no «ese pago si cae en el rango de fechas».
     */
    public function ignoresDateRange(): bool
    {
        return $this->isExact();
    }
}
