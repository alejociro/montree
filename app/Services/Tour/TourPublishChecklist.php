<?php

declare(strict_types=1);

namespace App\Services\Tour;

use App\Enums\TourStopKind;
use App\Models\Tour;

/**
 * Qué le falta a un tour para poder publicarse (D7).
 *
 * WHY: la lista vive en el servidor porque es el servidor quien rechaza la
 * activación. El riel del formulario evalúa lo mismo en vivo sobre valores sin
 * guardar —tiene que hacerlo, si no el checklist no reaccionaría al escribir—,
 * pero quién bloquea y quién solo recomienda se decide una vez, aquí, y
 * `ChangeTourStatusAction` consume esta misma lista: no hay forma de que la
 * pantalla prometa una condición que la activación no exige, ni al revés.
 *
 * Las paradas de recogida y regreso NO bloquean (D7): endurecerlas dejaría en
 * borrador a tours que hoy están activos la próxima vez que alguien los toque.
 */
final class TourPublishChecklist
{
    public const REQUIREMENT_GENERAL = 'general';

    public const REQUIREMENT_PRICING = 'pricing';

    public const REQUIREMENT_IMAGE = 'image';

    public const REQUIREMENT_GUIDE = 'guide';

    public const REQUIREMENT_SUMMARY = 'summary';

    public const REQUIREMENT_STOPS = 'stops';

    /**
     * @return array<int, array{id: string, label: string, done: bool, blocking: bool}>
     */
    public function for(Tour $tour): array
    {
        $tour->loadMissing(['images', 'stops']);

        return [
            [
                'id' => self::REQUIREMENT_GENERAL,
                'label' => __('Nombre y descripción'),
                'done' => $this->filled($tour->name) && $this->filled($tour->description),
                'blocking' => true,
            ],
            [
                'id' => self::REQUIREMENT_SUMMARY,
                'label' => __('Resumen corto'),
                'done' => $this->filled($tour->short_description),
                'blocking' => true,
            ],
            [
                'id' => self::REQUIREMENT_PRICING,
                'label' => __('Precio, cupo y duración'),
                'done' => (float) $tour->base_price > 0
                    && $tour->default_capacity >= 1
                    && $tour->duration_hours >= 1,
                'blocking' => true,
            ],
            [
                'id' => self::REQUIREMENT_IMAGE,
                'label' => __('Al menos una imagen'),
                'done' => $tour->images->isNotEmpty(),
                'blocking' => true,
            ],
            [
                'id' => self::REQUIREMENT_GUIDE,
                'label' => __('Guía por defecto'),
                'done' => $tour->default_guide_id !== null,
                'blocking' => true,
            ],
            [
                'id' => self::REQUIREMENT_STOPS,
                'label' => __('Parada de recogida y de regreso'),
                'done' => $this->hasStop($tour, TourStopKind::Pickup)
                    && $this->hasStop($tour, TourStopKind::Drop),
                'blocking' => false,
            ],
        ];
    }

    /**
     * Los identificadores que bloquean la activación y siguen sin cumplirse, en
     * el orden en que se listan: el primero es el que se le reporta al usuario.
     *
     * @return array<int, string>
     */
    public function pendingBlocking(Tour $tour): array
    {
        return array_values(array_map(
            static fn (array $requirement): string => (string) $requirement['id'],
            array_filter(
                $this->for($tour),
                static fn (array $requirement): bool => $requirement['blocking'] === true
                    && $requirement['done'] === false,
            ),
        ));
    }

    private function hasStop(Tour $tour, TourStopKind $kind): bool
    {
        return $tour->stops->contains(
            static fn ($stop): bool => $stop->kind === $kind,
        );
    }

    private function filled(?string $value): bool
    {
        return trim((string) $value) !== '';
    }
}
