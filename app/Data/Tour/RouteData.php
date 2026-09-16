<?php

declare(strict_types=1);

namespace App\Data\Tour;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La ficha de una ruta tal como llega del diálogo del producto: los campos de
 * cabecera, las paradas —ausentes cuando el formulario no las tocó— y la marca
 * de predeterminada, que no es una columna más sino una exclusión entre rutas.
 */
final readonly class RouteData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $stops
     */
    public function __construct(
        public array $attributes,
        public ?array $stops,
        public ?bool $isDefault,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $validated = $request->validated();
        $stops = array_key_exists('stops', $validated) ? (array) ($validated['stops'] ?? []) : null;

        unset($validated['stops'], $validated['is_default']);

        return new self(
            attributes: $validated,
            stops: $stops,
            isDefault: $request->has('is_default') ? $request->boolean('is_default') : null,
        );
    }
}
