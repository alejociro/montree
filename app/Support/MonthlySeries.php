<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Agrupación por mes hecha en PHP.
 *
 * WHY: `DATE_FORMAT` es de MySQL y `strftime` de SQLite. La suite corre sobre
 * SQLite y producción sobre MySQL, así que una gráfica agrupada en SQL sería
 * verde en la suite y estaría rota en el servidor. Las series son de 6 a 12
 * puntos sobre conjuntos acotados por fecha: agrupar en memoria sale gratis.
 */
final class MonthlySeries
{
    public const KEY_FORMAT = 'Y-m';

    /**
     * @return list<string>
     */
    public static function months(CarbonInterface $from, CarbonInterface $to): array
    {
        $cursor = CarbonImmutable::instance($from)->startOfMonth();
        $last = CarbonImmutable::instance($to)->startOfMonth();
        $months = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $months[] = $cursor->format(self::KEY_FORMAT);
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @param  iterable<array{0: CarbonInterface|null, 1: int|float|string}>  $rows
     * @return array<string, string>
     */
    public static function sum(iterable $rows): array
    {
        $totals = [];

        foreach ($rows as [$date, $amount]) {
            if ($date === null) {
                continue;
            }

            $key = $date->format(self::KEY_FORMAT);
            $totals[$key] = bcadd($totals[$key] ?? '0', self::decimal($amount), 2);
        }

        return $totals;
    }

    /**
     * @param  iterable<CarbonInterface|null>  $dates
     * @return array<string, int>
     */
    public static function count(iterable $dates): array
    {
        $totals = [];

        foreach ($dates as $date) {
            if ($date === null) {
                continue;
            }

            $key = $date->format(self::KEY_FORMAT);
            $totals[$key] = ($totals[$key] ?? 0) + 1;
        }

        return $totals;
    }

    /**
     * @param  list<string>  $months
     * @param  array<string, int|string>  $totals
     * @return list<array{month: string, label: string, value: int|string}>
     */
    public static function points(array $months, array $totals, int|string $default = 0): array
    {
        return array_map(
            static fn (string $month): array => [
                'month' => $month,
                'label' => self::label($month),
                'value' => $totals[$month] ?? $default,
            ],
            $months,
        );
    }

    public static function label(string $month): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('M Y');
    }

    private static function decimal(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
