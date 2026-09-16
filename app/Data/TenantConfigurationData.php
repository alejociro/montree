<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Campos de configuración que el panel envió en ESTA petición. Las reglas son
 * `sometimes`, así que la ausencia de una clave significa «no tocar», no «poner
 * en null»: por eso el DTO transporta un mapa disperso y no una propiedad por
 * columna.
 */
final readonly class TenantConfigurationData
{
    private const UPLOAD_KEYS = ['logo', 'favicon', 'hero_image', 'remove_logo', 'remove_hero_image'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(Arr::except($request->safe()->all(), self::UPLOAD_KEYS));
    }
}
