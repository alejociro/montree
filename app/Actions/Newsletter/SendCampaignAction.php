<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\NewsletterSubscriberStatus;
use App\Exceptions\NewsletterException;
use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterCampaignNotification;
use Illuminate\Support\Facades\Notification;

final class SendCampaignAction
{
    /**
     * @param  array{subject:string, body_html:string, preview_text?:string|null}  $data
     */
    public function handle(array $data, string $tenantName): int
    {
        $subscribers = NewsletterSubscriber::query()
            ->where('status', NewsletterSubscriberStatus::Active)
            ->get();

        if ($subscribers->isEmpty()) {
            throw NewsletterException::noRecipients();
        }

        foreach ($subscribers->chunk(50) as $chunk) {
            Notification::send($chunk, new NewsletterCampaignNotification(
                subject: $data['subject'],
                bodyHtml: $data['body_html'],
                previewText: $data['preview_text'] ?? null,
                tenantName: $tenantName,
            ));
        }

        return $subscribers->count();
    }
}
