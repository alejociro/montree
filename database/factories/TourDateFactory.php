<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TourDateStatus;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourDate>
 */
class TourDateFactory extends Factory
{
    protected $model = TourDate::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            // WHY (D9): un guía propio por salida es la única forma de que la
            // factory sea *incapaz* de producir un solape. Quien necesite un guía
            // concreto lo pasa explícito y se hace cargo de la disponibilidad.
            'guide_id' => User::factory(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+90 days'),
            // WHY: se deriva de `tours.duration_hours` en `configure()`. Sumar 4 h
            // fijas hacía que un tour de varios días «terminara» la misma tarde, y
            // la regla de disponibilidad se lo creería.
            'ends_at' => null,
            'capacity' => fake()->numberBetween(4, 20),
            'booked_count' => 0,
            'price_override' => null,
            'status' => TourDateStatus::Open,
            'notes' => null,
        ];
    }

    /**
     * @return $this
     */
    public function configure(): self
    {
        return $this->afterMaking(function (TourDate $tourDate): void {
            if ($tourDate->ends_at !== null) {
                return;
            }

            $tourDate->ends_at = $tourDate->starts_at->copy()->addHours($this->durationHoursFor($tourDate));
        });
    }

    public function full(): self
    {
        return $this->state(fn (array $attrs) => [
            'booked_count' => $attrs['capacity'] ?? 10,
            'status' => TourDateStatus::Full,
        ]);
    }

    public function past(): self
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(7),
            'ends_at' => null,
        ]);
    }

    public function withRoute(): self
    {
        return $this->state(fn () => [
            'route_id' => Route::factory(),
        ]);
    }

    public function withProvider(): self
    {
        return $this->state(fn () => [
            'provider_id' => Provider::factory(),
        ]);
    }

    public function withHotels(int $count = 1): self
    {
        return $this->afterCreating(function (TourDate $tourDate) use ($count): void {
            $tourDate->hotels()->attach(
                Hotel::factory()->count($count)->create()->pluck('id')->all(),
            );
        });
    }

    private function durationHoursFor(TourDate $tourDate): int
    {
        $tour = Tour::query()
            ->withoutGlobalScopes()
            ->whereKey($tourDate->tour_id)
            ->first(['id', 'duration_hours']);

        return max(1, (int) ($tour?->duration_hours ?? 4));
    }
}
