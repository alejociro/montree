<?php

declare(strict_types=1);

namespace App\Notifications\SuperAdmin;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

final class TenantUserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $actionUrl,
    ) {}

    public static function for(Tenant $tenant, string $token, string $email): self
    {
        $host = $tenant->domain ?? ($tenant->slug.'.'.Config::get('montree.super_admin_host'));
        $scheme = str_starts_with((string) Config::get('app.url'), 'https') ? 'https' : 'http';
        $url = $scheme.'://'.$host.'/reset-password/'.$token.'?email='.urlencode($email);

        return new self($tenant->name, $url);
    }

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
            ->subject(__('Te invitaron a :tenant', ['tenant' => $this->tenantName]))
            ->greeting(__('¡Hola!'))
            ->line(__('Fuiste agregado al equipo de :tenant en Montree.', ['tenant' => $this->tenantName]))
            ->action(__('Establecer mi contraseña'), $this->actionUrl)
            ->line(__('El enlace caduca en :minutes minutos. Si no esperabas esta invitación, podés ignorar este correo.', [
                'minutes' => Config::get('auth.passwords.users.expire', 60),
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tenant_user_invitation',
            'tenant_name' => $this->tenantName,
        ];
    }
}
