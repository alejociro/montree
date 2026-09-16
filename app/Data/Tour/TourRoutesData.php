<?php

declare(strict_types=1);

namespace App\Data\Tour;

use Illuminate\Foundation\Http\FormRequest;

final readonly class TourRoutesData
{
    /**
     * @param  array<int, TourRouteAssignment>  $assignments
     */
    public function __construct(public array $assignments) {}

    public static function fromRequest(FormRequest $request): ?self
    {
        if (! $request->has('routes')) {
            return null;
        }

        $rows = $request->validated('routes') ?? [];

        return new self(array_values(array_map(
            fn (array $row) => new TourRouteAssignment((int) $row['id'], (bool) ($row['is_default'] ?? false)),
            $rows,
        )));
    }
}
