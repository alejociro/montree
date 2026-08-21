<?php

declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\Models\Tour;
use App\Queries\TourOperationalSummaryQuery;
use Tests\TestCase;

/**
 * La suite corre sobre SQLite y la aplicación sobre MySQL, así que una consulta
 * puede pasar todos los tests y reventar en el navegador. Pasó: MySQL —hasta la
 * 9.6— rechaza `LIMIT` dentro de la subconsulta de un `IN` con el error 1235
 * («This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME
 * subquery'»), y el listado de tours devolvía 500 con las cifras operativas.
 *
 * Este test vigila la forma del SQL, no su resultado: la próxima salida es una
 * fila, se compara con `=`, y ningún `IN` de esta consulta puede llevar `LIMIT`.
 */
final class TourOperationalSummarySqlTest extends TestCase
{
    public function test_the_next_departure_is_compared_as_a_scalar_not_with_an_in_subquery(): void
    {
        $sql = $this->operationalSql();

        $this->assertStringContainsString('tour_date_id = (select', $sql);
        $this->assertStringNotContainsString('tour_date_id in (select', $sql);
    }

    public function test_no_in_subquery_of_the_listing_carries_a_limit(): void
    {
        $sql = $this->operationalSql();

        foreach ($this->inSubqueries($sql) as $subquery) {
            $this->assertStringNotContainsString('limit', $subquery, "Un `IN` con `LIMIT` rompe en MySQL: {$subquery}");
        }
    }

    /** SQL del listado con las cifras operativas, sin comillas de identificador. */
    private function operationalSql(): string
    {
        $query = (new TourOperationalSummaryQuery)->applyTo(Tour::query());

        return str_replace(['`', '"'], '', $query->toSql());
    }

    /**
     * Cuerpo de cada `in (…)` del SQL, con los paréntesis balanceados.
     *
     * @return array<int, string>
     */
    private function inSubqueries(string $sql): array
    {
        $found = [];
        $offset = 0;

        while (($start = strpos($sql, 'in (', $offset)) !== false) {
            $depth = 0;
            $length = strlen($sql);

            for ($i = $start + 3; $i < $length; $i++) {
                $depth += (int) ($sql[$i] === '(') - (int) ($sql[$i] === ')');

                if ($depth === 0) {
                    $found[] = substr($sql, $start, $i - $start + 1);
                    $offset = $i;

                    break;
                }
            }

            if ($depth !== 0) {
                break;
            }
        }

        return $found;
    }
}
