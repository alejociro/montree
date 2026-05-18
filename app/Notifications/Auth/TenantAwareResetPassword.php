<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Tenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

final class TenantAwareResetPassword extends ResetPassword
{
    public function __construct(
        string $token,
        private readonly string $tenantName,
        private readonly ?string $primaryColor,
        private readonly ?string $logoUrl,
    ) {
        parent::__construct($token);
    }

    public static function fromTenant(string $token, Tenant $tenant): self
    {
        $tenant->loadMissing('configuration');

        $logoPath = $tenant->configuration?->logo_path;

        return new self(
            token: $token,
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
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Restablece tu contraseña en :tenant', ['tenant' => $this->tenantName]))
            ->view('emails.reset-password', [
                'tenantName' => $this->tenantName,
                'primaryColor' => $this->primaryColor ?? '#16a34a',
                'logoUrl' => $this->logoUrl,
                'resetUrl' => $resetUrl,
                'recipientName' => $this->resolveRecipientName($notifiable),
                'expiresInMinutes' => Config::get('auth.passwords.users.expire', 60),
            ]);
    }

    /**
     * @param  object  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if (self::$createUrlCallback) {
            return call_user_func(self::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    private function resolveRecipientName(object $notifiable): string
    {
        if (property_exists($notifiable, 'name') && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        return __('viajero/a');
    }
}
