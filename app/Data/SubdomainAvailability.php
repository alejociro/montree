<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SubdomainAvailabilityReason;
use JsonSerializable;

final readonly class SubdomainAvailability implements JsonSerializable
{
    public function __construct(
        public string $slug,
        public bool $available,
        public ?SubdomainAvailabilityReason $reason = null,
    ) {}

    /**
     * @return array{slug: string, available: bool, reason: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'available' => $this->available,
            'reason' => $this->reason?->value,
        ];
    }
}
