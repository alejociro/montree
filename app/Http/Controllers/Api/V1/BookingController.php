<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\SyncBookingTravelersAction;
use App\Enums\UserRole;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\SyncBookingTravelersRequest;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class BookingController extends Controller
{
    public function __construct(
        private CreateBookingAction $createBooking,
        private SyncBookingTravelersAction $syncTravelers,
        private AttachUserToTenant $attachUserToTenant,
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $isNewAccount = false;

        if ($user === null) {
            $user = $this->resolveGuestUser($request, $isNewAccount);
            Auth::login($user);
            $request->session()->regenerate();
        }

        if ($isNewAccount) {
            $request->session()->put('booking_new_account', true);
        }

        $booking = $this->createBooking->handle($user, $request->validated());

        return (new BookingResource($booking))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $bookingNumber): BookingResource
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with(['tour', 'tourDate', 'travelers', 'promotion'])
            ->first();

        if ($booking === null) {
            throw BookingException::notFound();
        }

        return new BookingResource($booking);
    }

    public function syncTravelers(SyncBookingTravelersRequest $request): BookingResource
    {
        $booking = $request->booking();

        if ($booking === null) {
            throw BookingException::notFound();
        }

        $booking = $this->syncTravelers->handle($booking, $request->validated('travelers'));

        return new BookingResource($booking->loadMissing(['tour', 'tourDate', 'promotion']));
    }

    private function resolveGuestUser(StoreBookingRequest $request, bool &$isNewAccount): User
    {
        $email = (string) $request->validated('email');
        $tenant = Tenant::current();

        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) $request->validated('full_name'),
                'phone' => $request->validated('phone'),
                'password' => Hash::make(Str::random(32)),
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->markEmailAsVerified();
            $isNewAccount = true;
            Password::sendResetLink(['email' => $email]);
        }

        if ($tenant !== null) {
            $this->attachUserToTenant->handle($user, $tenant, UserRole::Customer, 'guest_booking');
        }

        return $user;
    }
}
