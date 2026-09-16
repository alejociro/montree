<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Exceptions\LogisticsRecordInUseException;
use App\Models\Route;

/**
 * Los productos pesan tanto como las salidas: desasociar una ruta de un producto
 * es una decisión del catálogo, no un efecto colateral de vaciar logística.
 */
final class DeleteRouteAction
{
    public function execute(Route $route): void
    {
        $tours = $route->tours()->orderBy('name')->pluck('tours.name')->all();

        if ($tours !== []) {
            throw LogisticsRecordInUseException::routeUsedByTours($tours);
        }

        $departures = $route->tourDates()->count();

        if ($departures > 0) {
            throw LogisticsRecordInUseException::routeUsedByDepartures($departures);
        }

        $route->delete();
    }
}
