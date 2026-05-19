<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewsletterCampaignNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $bodyHtml,
        public readonly ?string $previewText,
        public readonly string $tenantName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $unsubscribeToken = $notifiable instanceof NewsletterSubscriber ? $notifiable->unsubscribe_token : '';
        $unsubscribeUrl = url('/unsubscribe/'.$unsubscribeToken);

        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.newsletter.campaign', [
                'subject' => $this->subject,
                'bodyHtml' => $this->bodyHtml,
                'previewText' => $this->previewText,
                'tenantName' => $this->tenantName,
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);
    }
}
