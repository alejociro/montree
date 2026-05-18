<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Newsletter\SubscribeAction;
use App\Actions\Newsletter\UnsubscribeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NewsletterController extends Controller
{
    public function __construct(
        private SubscribeAction $subscribe,
        private UnsubscribeAction $unsubscribe,
    ) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $subscriber = $this->subscribe->handle($request->validated());

        return new JsonResponse([
            'data' => [
                'email' => $subscriber->email,
                'subscribed_at' => $subscriber->subscribed_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function unsubscribeByToken(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $subscriber = $this->unsubscribe->handle((string) $request->input('token'));

        return new JsonResponse([
            'data' => ['email' => $subscriber->email, 'status' => 'unsubscribed'],
        ]);
    }
}
