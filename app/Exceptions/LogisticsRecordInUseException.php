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
    private const MAX_LISTED_DEPARTURES = 3;

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
     * @param  list<string>  $departures  fechas de las salidas que la retienen
     */
    public static function routeUsedByDepartures(array $departures): self
    {
        return new self(trans_choice(
            '{1}No se puede eliminar: la ruta está en uso por :count salida (:departures).|[2,*]No se puede eliminar: la ruta está en uso por :count salidas (:departures).',
            count($departures),
            ['count' => count($departures), 'departures' => self::summarize($departures)],
        ));
    }

    /**
     * @param  list<string>  $departures
     */
    private static function summarize(array $departures): string
    {
        $listed = array_slice($departures, 0, self::MAX_LISTED_DEPARTURES);
        $rest = count($departures) - count($listed);

        if ($rest === 0) {
            return implode(', ', $listed);
        }

        return __(':list y :count más', ['list' => implode(', ', $listed), 'count' => $rest]);
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
