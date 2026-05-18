<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\NewsletterSubscriberStatus;
use App\Exceptions\NewsletterException;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

final class SubscribeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): NewsletterSubscriber
    {
        $email = mb_strtolower(trim((string) $data['email']));

        $existing = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->status === NewsletterSubscriberStatus::Active) {
                throw NewsletterException::alreadySubscribed();
            }
            $existing->update([
                'status' => NewsletterSubscriberStatus::Active,
                'name' => $data['name'] ?? $existing->name,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);

            return $existing->fresh();
        }

        return NewsletterSubscriber::query()->create([
            'email' => $email,
            'name' => $data['name'] ?? null,
            'status' => NewsletterSubscriberStatus::Active,
            'unsubscribe_token' => Str::random(40),
            'subscribed_at' => now(),
            'source' => 'website',
        ]);
    }
}
