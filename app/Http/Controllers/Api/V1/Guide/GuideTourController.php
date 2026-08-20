<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guide;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Passenger\GuideTourResource;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Detalle de tour en lectura para el guía (D1).
 *
 * WHY: el alcance se filtra por **pertenencia**, no por permiso — alcanza los
 * tours donde tiene al menos una salida asignada. Un tour de su misma agencia
 * sin salida suya es 403, y las salidas de otros guías no se listan.
 */
final class GuideTourController extends Controller
{
    public function show(Request $request, Tour $tour): GuideTourResource
    {
        $departures = $tour->dates()
            ->where('guide_id', $request->user()->id)
            ->withSum(['bookings as passengers_count' => fn (Builder $query) => $query
                ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])], 'travelers_count')
            ->orderBy('starts_at')
            ->get();

        abort_if($departures->isEmpty(), 403);

        return new GuideTourResource($tour->load(['category:id,name', 'images', 'itineraries', 'stops']), $departures);
    }
}
