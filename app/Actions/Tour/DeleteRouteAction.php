<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourDateStatus;
use App\Exceptions\LogisticsRecordInUseException;
use App\Models\Route;
use App\Models\TourDate;
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
            ->orderBy('starts_at')
            ->get(['id', 'starts_at'])
            ->map(fn (TourDate $departure): string => $departure->starts_at->format('d/m/Y H:i'))
            ->all();

        if ($operating !== []) {
            throw LogisticsRecordInUseException::routeUsedByDepartures($operating);
        }

        DB::transaction(function () use ($route): void {
            $route->tourDates()->update(['route_id' => null]);
            $route->delete();
        });
    }
}
