<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourDateStatus;
use App\Exceptions\LogisticsRecordInUseException;
use App\Models\Route;
use Illuminate\Support\Facades\DB;

/**
 * Una ruta que todavía se va a operar no se borra. Las salidas pasadas y las
 * canceladas sí la sueltan: ahí la ruta ya no dirige nada y el dato es historia
 * que el borrado del catálogo no puede retener (spec §I).
 */
final class DeleteRouteAction
{
    public function execute(Route $route): void
    {
        $operating = $route->tourDates()
            ->where('starts_at', '>', now())
            ->where('status', '!=', TourDateStatus::Cancelled)
            ->count();

        if ($operating > 0) {
            throw LogisticsRecordInUseException::routeUsedByDepartures($operating);
        }

        DB::transaction(function () use ($route): void {
            $route->tourDates()->update(['route_id' => null]);
            $route->delete();
        });
    }
}
