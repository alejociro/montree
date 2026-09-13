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
use App\Notifications\Auth\BookingAccessLinkNotification;
use App\Services\Auth\CrossHostLoginHandoff;
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
        private CrossHostLoginHandoff $handoff,
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $isNewAccount = false;
        $requiresEmailAccess = false;

        if ($user === null) {
            $user = $this->resolveGuestUser($request, $isNewAccount);

            // WHY (S-01): escribir un correo no prueba poseerlo. Solo la cuenta que
            // nace en este mismo checkout puede quedar con sesión abierta; si el correo
            // ya tenía dueño, la reserva se crea a su nombre pero el acceso sale por un
            // enlace de un solo uso a su bandeja. Antes se hacía `Auth::login()` sobre
            // la cuenta encontrada: cualquiera entraba escribiendo el correo ajeno.
            if ($isNewAccount) {
                Auth::login($user);
                $request->session()->regenerate();
                $request->session()->put('booking_new_account', true);
            } else {
                $requiresEmailAccess = true;
            }
        }

        $booking = $this->createBooking->handle($user, $request->validated());

        if ($requiresEmailAccess) {
            return $this->respondWithAccessLink($user, $booking);
        }

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

        // WHY (S-01): `AttachUserToTenant` hace `syncRoles`, que REEMPLAZA los roles de
        // la persona en esta agencia. Si ya es miembro —fundador, admin o guía— volver a
        // engancharla desde un checkout de invitado la degradaba a `customer`: bastaba
        // con escribir su correo para dejarla sin panel. Solo se engancha a quien todavía
        // no pertenece al tenant.
        if ($tenant !== null && ! $user->belongsToTenant($tenant)) {
            $this->attachUserToTenant->handle($user, $tenant, UserRole::Customer, 'guest_booking');
        }

        return $user;
    }

    /**
     * WHY: la reserva quedó a nombre del dueño del correo, así que la respuesta no puede
     * traer sus datos ni un número que él no ha visto todavía: solo confirma el envío. El
     * enlace de acceso —de un uso y con el mismo hold de 30 minutos de la reserva— es lo
     * único que abre sesión.
     */
    private function respondWithAccessLink(User $user, Booking $booking): JsonResponse
    {
        $minutesValid = intdiv(CrossHostLoginHandoff::EMAIL_TTL_SECONDS, 60);

        $token = $this->handoff->issue(
            $user,
            '/bookings/'.$booking->booking_number,
            CrossHostLoginHandoff::EMAIL_TTL_SECONDS,
        );

        $user->notify(new BookingAccessLinkNotification(
            accessUrl: route('auth.handoff', ['token' => $token]),
            bookingNumber: $booking->booking_number,
            tourName: $booking->tour->name,
            minutesValid: $minutesValid,
        ));

        return response()->json([
            'data' => [
                'requires_email_access' => true,
                'email' => $user->email,
                'minutes_valid' => $minutesValid,
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
