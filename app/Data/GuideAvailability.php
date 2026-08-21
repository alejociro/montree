<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Un guía del tenant con los días que ya tiene ocupados dentro del rango
 * consultado. Alimenta el select del panel, que no ofrece lo que la regla
 * `GuideIsAvailable` va a rechazar (D9).
 */
final readonly class GuideAvailability
{
    /**
     * @param  array<int, GuideBusyBlock>  $busy
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $busy,
    ) {}

    /**
     * @return array{id:int, name:string, busy:array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'busy' => array_map(static fn (GuideBusyBlock $block): array => $block->toArray(), $this->busy),
        ];
    }
}
