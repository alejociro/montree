<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Un registro de logística en uso no se borra: se nombra quién lo usa (spec §G).
 *
 * Cada constructor nombrado lleva su propia redacción porque el detalle es lo
 * útil del error —qué producto o cuántas salidas lo retienen—, no el hecho de
 * que esté en uso.
 */
final class LogisticsRecordInUseException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $tourNames
     */
    public static function routeUsedByTours(array $tourNames): self
    {
        return new self(__('No se puede eliminar: la ruta está asociada a :tours.', [
            'tours' => implode(', ', $tourNames),
        ]));
    }

    public static function routeUsedByDepartures(int $count): self
    {
        return new self(trans_choice(
            '{1}No se puede eliminar: la ruta está en uso por :count salida.|[2,*]No se puede eliminar: la ruta está en uso por :count salidas.',
            $count,
            ['count' => $count],
        ));
    }

    public static function hotelUsedByDepartures(int $count): self
    {
        return new self(trans_choice(
            '{1}No se puede eliminar: el hotel está en uso por :count salida.|[2,*]No se puede eliminar: el hotel está en uso por :count salidas.',
            $count,
            ['count' => $count],
        ));
    }

    public static function providerUsedByDepartures(int $count): self
    {
        return new self(trans_choice(
            '{1}No se puede eliminar: el proveedor está en uso por :count salida.|[2,*]No se puede eliminar: el proveedor está en uso por :count salidas.',
            $count,
            ['count' => $count],
        ));
    }
}
