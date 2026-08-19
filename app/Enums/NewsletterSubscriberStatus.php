<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsletterSubscriberStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Unsubscribed => __('Unsubscribed'),
            self::Bounced => __('Bounced'),
        };
    }
}
