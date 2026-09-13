<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsletterSubscriber
 *
 * WHY: el listado devolvía el modelo crudo, y con él `unsubscribe_token` —la
 * llave con la que cualquiera puede dar de baja a esa persona sin estar
 * autenticado—. El recurso fija qué sale.
 */
final class NewsletterSubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status->value,
            'source' => $this->source,
            'subscribed_at' => $this->subscribed_at?->toIso8601String(),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
        ];
    }
}
