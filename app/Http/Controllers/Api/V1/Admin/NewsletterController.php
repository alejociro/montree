<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Newsletter\SendCampaignAction;
use App\Enums\NewsletterSubscriberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Newsletter\SendCampaignRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NewsletterController extends Controller
{
    public function __construct(private SendCampaignAction $sendCampaign) {}

    public function index(Request $request): JsonResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->orderByDesc('id')
            ->paginate(20);
        $active = NewsletterSubscriber::query()
            ->where('status', NewsletterSubscriberStatus::Active)
            ->count();

        return new JsonResponse([
            'data' => $subscribers->items(),
            'meta' => [
                'total_active' => $active,
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
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
}
