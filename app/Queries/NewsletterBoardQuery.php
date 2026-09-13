<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\NewsletterSubscriberStatus;
use App\Models\NewsletterSubscriber;

/**
 * Cifras de cabecera del newsletter.
 *
 * NOTA: el handoff pedía además «Campañas enviadas», pero no existe tabla de
 * campañas —`SendCampaignAction` notifica y no deja registro—, así que ese KPI
 * no se inventa: en su lugar va el total histórico de suscriptores, que sí sale
 * de un dato real. Para tenerlo hay que persistir cada envío.
 */
final class NewsletterBoardQuery
{
    /**
     * @return array{active:int,total:int,joined_this_month:int,left_this_month:int}
     */
    public function stats(): array
    {
        $startOfMonth = now()->startOfMonth();

        return [
            'active' => NewsletterSubscriber::query()
                ->where('status', NewsletterSubscriberStatus::Active)
                ->count(),
            'total' => NewsletterSubscriber::query()->count(),
            'joined_this_month' => NewsletterSubscriber::query()
                ->where('subscribed_at', '>=', $startOfMonth)
                ->count(),
            'left_this_month' => NewsletterSubscriber::query()
                ->where('unsubscribed_at', '>=', $startOfMonth)
                ->count(),
        ];
    }
}
