<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class FailedPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponseContract
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request): Response
    {
        return $this->buildSuccessResponse($request);
    }

    /**
     * WHY: Spec requires no email enumeration — always pretend success even
     * when the email is unknown. We swallow the failure and respond as if a
     * reset link was sent.
     */
    private function buildSuccessResponse(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['status' => __('passwords.sent')]);
        }

        return back()->with('status', __('passwords.sent'));
    }
}
