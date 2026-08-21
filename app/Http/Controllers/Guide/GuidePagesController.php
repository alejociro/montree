<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guide;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuidePagesController extends Controller
{
    public function schedule(): Response
    {
        return Inertia::render('Guide/Schedule');
    }

    /**
     * Planilla de una salida del guía.
     *
     * WHY: la página solo lleva los identificadores; el contenido lo trae la
     * API (`GET /api/v1/guide/tour-dates/{tourDate}/passengers`), que es donde
     * vive la máscara del dato de salud y la comprobación de pertenencia. Aquí
     * se repite la guarda de pertenencia para que la ruta no responda 200 con
     * una pantalla que después va a fallar: la regla de oro del menú (F018) es
     * que si el item aparece, la ruta responde 200.
     */
    public function passengers(Request $request, TourDate $tourDate): Response
    {
        abort_if($tourDate->guide_id !== $request->user()->id, 403);

        $tourDate->load('tour:id,name,slug');

        return Inertia::render('Guide/Passengers', [
            'departure' => [
                'id' => $tourDate->id,
                'starts_at' => $tourDate->starts_at->toIso8601String(),
                'ends_at' => $tourDate->ends_at?->toIso8601String(),
                'status' => $tourDate->status->value,
                'tour' => [
                    'id' => $tourDate->tour->id,
                    'name' => $tourDate->tour->name,
                ],
            ],
        ]);
    }

    /**
     * Detalle de tour en lectura (D1). El alcance es por **pertenencia**: al
     * menos una salida asignada en este tour.
     */
    public function tour(Request $request, Tour $tour): Response
    {
        abort_if(
            ! $tour->dates()->where('guide_id', $request->user()->id)->exists(),
            403,
        );

        return Inertia::render('Guide/TourShow', [
            'tourId' => $tour->id,
        ]);
    }
}
