<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NewsletterSubscriberStatus;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => NewsletterSubscriberStatus::Active,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
            'source' => 'footer_form',
        ];
    }

    public function unsubscribed(): self
    {
        return $this->state(fn () => [
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);
    }
}
