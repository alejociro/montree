<?php

declare(strict_types=1);

namespace App\Rules;

use App\Queries\GuideAvailabilityQuery;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un guía no puede tener dos salidas cruzadas en días calendario (D9).
 *
 * WHY: la regla vive en un solo objeto porque son **tres** los caminos que
 * asignan guía —crear salida, editar salida y el `PATCH` de asignación—. Si
 * cada uno validara por su cuenta, la regla se saltaría por el que se olvide.
 */
final readonly class GuideIsAvailable implements ValidationRule
{
    public function __construct(
        private CarbonInterface $startsAt,
        private CarbonInterface $endsAt,
        private ?int $excludeTourDateId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $busy = app(GuideAvailabilityQuery::class)
            ->busyFor((int) $value, $this->startsAt, $this->endsAt, $this->excludeTourDateId);

        if ($busy === []) {
            return;
        }

        $fail(__('Ocupado :block.', ['block' => $busy[0]->label()]));
    }
}
