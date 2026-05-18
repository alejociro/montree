<?php

declare(strict_types=1);

namespace App\Notifications\SuperAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantRestoredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tenantName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Tu agencia ":tenant" fue restablecida', ['tenant' => $this->tenantName]))
            ->view('emails.super-admin.tenant-restored', [
                'tenantName' => $this->tenantName,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tenant_restored',
            'tenant_name' => $this->tenantName,
        ];
    }
}
