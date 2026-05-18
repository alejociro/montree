<?php

declare(strict_types=1);

namespace App\Notifications\SuperAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantPlanChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $previousPlan,
        public readonly string $newPlan,
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
            ->subject(__('Cambio de plan en ":tenant"', ['tenant' => $this->tenantName]))
            ->view('emails.super-admin.tenant-plan-changed', [
                'tenantName' => $this->tenantName,
                'previousPlan' => $this->previousPlan,
                'newPlan' => $this->newPlan,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tenant_plan_changed',
            'tenant_name' => $this->tenantName,
            'previous_plan' => $this->previousPlan,
            'new_plan' => $this->newPlan,
        ];
    }
}
