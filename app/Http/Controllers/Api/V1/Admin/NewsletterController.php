<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Newsletter\SendCampaignAction;
use App\Actions\Newsletter\UnsubscribeSubscriberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Newsletter\SendCampaignRequest;
use App\Http\Requests\Admin\Newsletter\SendCampaignTestRequest;
use App\Http\Resources\Admin\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Models\Tenant;
use App\Notifications\NewsletterCampaignNotification;
use App\Queries\NewsletterBoardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

final class NewsletterController extends Controller
{
    public function __construct(
        private SendCampaignAction $sendCampaign,
        private UnsubscribeSubscriberAction $unsubscribe,
        private NewsletterBoardQuery $board,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('newsletter.view');

        $perPage = min(max((int) $request->integer('per_page', 10), 1), 100);

        $subscribers = NewsletterSubscriber::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => NewsletterSubscriberResource::collection($subscribers->items())->resolve(),
            'stats' => $this->board->stats(),
            'meta' => [
                'total_active' => $this->board->stats()['active'],
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'per_page' => $subscribers->perPage(),
                'from' => $subscribers->firstItem(),
                'to' => $subscribers->lastItem(),
                'total' => $subscribers->total(),
            ],
        ]);
    }

    public function send(SendCampaignRequest $request): JsonResponse
    {
        $count = $this->sendCampaign->handle(
            $request->validated(),
            (string) (Tenant::current()?->name ?? config('app.name')),
        );

        return new JsonResponse(['data' => ['queued_count' => $count]], Response::HTTP_ACCEPTED);
    }

    /**
     * Envío de prueba a la propia cuenta.
     *
     * WHY: la campaña sale a toda la lista de una y no hay borrador ni
     * cancelación. Poder verla primero en la bandeja propia es la única forma
     * de revisar el asunto, el texto de vista previa y el cuerpo tal como los
     * verá el suscriptor. No pasa por `SendCampaignAction` a propósito: esa
     * acción es «a todos los activos» y aquí el destinatario es uno solo.
     */
    public function sendTest(SendCampaignTestRequest $request): JsonResponse
    {
        $user = $request->user();

        Notification::route('mail', $user?->email)->notify(new NewsletterCampaignNotification(
            subject: (string) $request->validated('subject'),
            bodyHtml: (string) $request->validated('body_html'),
            previewText: $request->validated('preview_text'),
            tenantName: (string) (Tenant::current()?->name ?? config('app.name')),
        ));

        return new JsonResponse(['data' => ['sent_to' => $user?->email]], Response::HTTP_ACCEPTED);
    }

    public function unsubscribeSubscriber(NewsletterSubscriber $subscriber): JsonResponse
    {
        Gate::authorize('newsletter.send');

        $updated = $this->unsubscribe->handle($subscriber);

        return new JsonResponse(['data' => (new NewsletterSubscriberResource($updated))->resolve()]);
    }
}
