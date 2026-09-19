<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Set curado de íconos Lucide que una agencia puede darle a una categoría.
 *
 * WHY: el valor es el nombre kebab-case del ícono de `lucide-vue-next`, no un
 * código propio: el front lo resuelve contra el paquete sin tabla intermedia.
 * La lista es cerrada para que el selector quepa en una grilla y para que el
 * front pueda empaquetar solo estos íconos.
 */
enum CategoryIcon: string
{
    case Mountain = 'mountain';
    case Compass = 'compass';
    case Palette = 'palette';
    case Utensils = 'utensils';
    case Binoculars = 'binoculars';
    case Bike = 'bike';
    case Waves = 'waves';
    case Tent = 'tent';
    case TreePine = 'tree-pine';
    case Camera = 'camera';
    case Fish = 'fish';
    case Sailboat = 'sailboat';
    case Footprints = 'footprints';
    case Flame = 'flame';
    case Sun = 'sun';
    case Map = 'map';

    public function label(): string
    {
        return match ($this) {
            self::Mountain => __('Montaña'),
            self::Compass => __('Aventura'),
            self::Palette => __('Cultura'),
            self::Utensils => __('Gastronomía'),
            self::Binoculars => __('Avistamiento'),
            self::Bike => __('Ciclismo'),
            self::Waves => __('Río y mar'),
            self::Tent => __('Camping'),
            self::TreePine => __('Bosque'),
            self::Camera => __('Fotografía'),
            self::Fish => __('Pesca'),
            self::Sailboat => __('Navegación'),
            self::Footprints => __('Senderismo'),
            self::Flame => __('Fogata'),
            self::Sun => __('Playa'),
            self::Map => __('Expedición'),
        };
    }
}
