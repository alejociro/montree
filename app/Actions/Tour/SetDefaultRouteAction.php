<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\Route;
use Illuminate\Support\Facades\DB;

/**
 * Una sola predeterminada por producto. La exclusión se resuelve aquí y no en
 * la base: el índice parcial que la expresaría no es portable entre MySQL y
 * SQLite.
 */
final class SetDefaultRouteAction
{
    public function execute(Route $route): void
    {
        DB::transaction(function () use ($route): void {
            Route::query()
                ->where('tour_id', $route->tour_id)
                ->whereKeyNot($route->getKey())
                ->update(['is_default' => false]);

            $route->forceFill(['is_default' => true])->save();
        });
    }
}
