<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\NewsletterSubscriberStatus;
use App\Exceptions\NewsletterException;
use App\Models\NewsletterSubscriber;

final class UnsubscribeAction
{
    public function handle(string $token): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if ($subscriber === null) {
            throw NewsletterException::invalidToken();
        }

        $subscriber->update([
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);

        return $subscriber->fresh();
    }
}
