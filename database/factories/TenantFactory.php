<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(4));

        return [
            'name' => $name,
            'slug' => $slug,
            'domain' => $slug.'.montree.test',
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => TenantStatus::Active,
            'suspended_at' => null,
        ];
    }

    public function suspended(): self
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Pending,
        ]);
    }
}
