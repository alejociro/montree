<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Tenant;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

final class TenantAwareVerifyEmail extends VerifyEmail
{
    public function __construct(
        private readonly string $tenantName,
        private readonly ?string $primaryColor,
        private readonly ?string $logoUrl,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $tenant->loadMissing('configuration');

        $logoPath = $tenant->configuration?->logo_path;

        return new self(
            tenantName: $tenant->name,
            primaryColor: $tenant->configuration?->primary_color,
            logoUrl: $logoPath !== null ? asset('storage/'.ltrim($logoPath, '/')) : null,
        );
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Verifica tu cuenta en :tenant', ['tenant' => $this->tenantName]))
            ->view('emails.verify-email', [
                'tenantName' => $this->tenantName,
                'primaryColor' => $this->primaryColor ?? '#16a34a',
                'logoUrl' => $this->logoUrl,
                'verificationUrl' => $verificationUrl,
                'recipientName' => $this->resolveRecipientName($notifiable),
                'expiresInMinutes' => Config::get('auth.verification.expire', 60),
            ]);
    }

    /**
     * @param  object  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    private function resolveRecipientName(object $notifiable): string
    {
        if (property_exists($notifiable, 'name') && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        return __('viajero/a');
    }
}
