<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\NewsletterSubscriberStatus;
use App\Models\NewsletterSubscriber;

/**
 * Da de baja a un suscriptor desde el panel.
 *
 * WHY: hasta ahora solo se podía dar de baja quien tuviera el enlace del
 * correo. La agencia recibe la petición por WhatsApp o por teléfono y no tenía
 * forma de cumplirla. El registro no se borra —se marca— para que un alta
 * posterior no se cuente dos veces y quede el rastro de cuándo se fue.
 */
final class UnsubscribeSubscriberAction
{
    public function handle(NewsletterSubscriber $subscriber): NewsletterSubscriber
    {
        if ($subscriber->status !== NewsletterSubscriberStatus::Unsubscribed) {
            $subscriber->forceFill([
                'status' => NewsletterSubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ])->save();
        }

        return $subscriber->fresh() ?? $subscriber;
    }
}
