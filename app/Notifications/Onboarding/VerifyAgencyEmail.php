<?php

declare(strict_types=1);

namespace App\Notifications\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Spatie\Multitenancy\Jobs\NotTenantAware;

final class VerifyAgencyEmail extends Notification implements NotTenantAware, ShouldQueue
{
    use Queueable;

    private const EXPIRES_IN_MINUTES = 60;

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
                'expiresInMinutes' => self::EXPIRES_IN_MINUTES,
            ]);
    }

    private function verificationUrl(): string
    {
        $scheme = $this->scheme();

        URL::useOrigin($scheme.'://'.$this->platformHost());
        URL::forceScheme($scheme);

        try {
            return URL::temporarySignedRoute(
                'onboarding.verify',
                now()->addMinutes(self::EXPIRES_IN_MINUTES),
                ['tenant' => $this->tenantId, 'user' => $this->userId],
            );
        } finally {
            URL::forceScheme(null);
            URL::useOrigin(null);
        }
    }

    private function scheme(): string
    {
        $scheme = (string) parse_url((string) Config::get('app.url'), PHP_URL_SCHEME);

        return App::isLocal() && $scheme !== '' ? $scheme : 'https';
    }

    private function platformHost(): string
    {
        $host = (string) Config::get('montree.platform_host');
        $port = parse_url((string) Config::get('app.url'), PHP_URL_PORT);

        return is_int($port) ? $host.':'.$port : $host;
    }

    private function recipientName(object $notifiable): string
    {
        if (property_exists($notifiable, 'name') && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        return __('fundador/a');
    }
}
