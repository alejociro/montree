<?php

declare(strict_types=1);

namespace App\Notifications\SuperAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly ?string $reason,
    ) {}

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
            ->subject(__('Tu agencia ":tenant" fue suspendida', ['tenant' => $this->tenantName]))
            ->view('emails.super-admin.tenant-suspended', [
                'tenantName' => $this->tenantName,
                'reason' => $this->reason,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tenant_suspended',
            'tenant_name' => $this->tenantName,
            'reason' => $this->reason,
        ];
    }
}
