<?php

declare(strict_types=1);

namespace App\Notifications\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Branded email sent to an agency founder. The signed link verifies their email
 * and activates the agency on the platform host, then hands them off to their own
 * subdomain already logged in.
 *
 * WHY NotTenantAware: onboarding runs on the platform host, where ResolveTenant
 * forgets the current tenant, so the queued job carries no `tenantId`. With
 * `queues_are_tenant_aware_by_default` the worker would delete it and throw
 * before it ever runs — silently, without a `failed_jobs` row. Nothing here
 * needs a current tenant: the payload is scalars, `User` has no tenant global
 * scope, and every `switch_tenant_task` is disabled.
 */
final class VerifyAgencyEmail extends Notification implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $userId,
        private readonly string $agencyName,
        private readonly string $primaryColor,
    ) {}

    public static function for(Tenant $tenant, User $founder): self
    {
        $tenant->loadMissing('configuration');

        return new self(
            tenantId: (int) $tenant->getKey(),
            userId: (int) $founder->getKey(),
            agencyName: $tenant->name,
            primaryColor: $tenant->configuration?->primary_color ?? '#16a34a',
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Activa tu agencia en MONTREE'))
            ->view('emails.verify-agency', [
                'agencyName' => $this->agencyName,
                'primaryColor' => $this->primaryColor,
                'recipientName' => $this->recipientName($notifiable),
                'verificationUrl' => $this->verificationUrl(),
                'expiresInMinutes' => 60,
            ]);
    }

    private function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'onboarding.verify',
            now()->addMinutes(60),
            ['tenant' => $this->tenantId, 'user' => $this->userId],
        );
    }

    private function recipientName(object $notifiable): string
    {
        if (property_exists($notifiable, 'name') && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        return __('fundador/a');
    }
}
