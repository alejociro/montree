<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\AdminBookingIndexRequest;
use App\Http\Resources\Admin\BookingSummaryResource;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BookingController extends Controller
{
    public function index(AdminBookingIndexRequest $request): AnonymousResourceCollection
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, 'No tenant for this host.');

        $query = Booking::query()
            ->with(['user:id,name,email', 'tour:id,name', 'tourDate:id,starts_at'])
            ->latest();

        if ($request->attentionOnly()) {
            $query->where(function ($builder): void {
                $builder->where('status', BookingStatus::PendingPayment->value)
                    ->orWhere(function ($inner): void {
                        $inner->where('status', BookingStatus::Confirmed->value)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now()->addHours(24));
                    });
            });
        }

        $bookings = $query->paginate($request->perPage());

        return BookingSummaryResource::collection($bookings);
    }
}
