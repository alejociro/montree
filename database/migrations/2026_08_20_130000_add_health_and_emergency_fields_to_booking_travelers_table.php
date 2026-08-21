<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de salud y contacto de emergencia por persona.
     *
     * El contacto de emergencia se capturaba por reserva, dentro del JSON
     * `bookings.contact_snapshot` (`CreateBookingAction:92`). El requerimiento lo
     * pide por pasajero: cuatro personas de dos familias no comparten teléfono.
     * Esta migración lo traslada al primer viajero de cada reserva que lo tenga.
     */
    public function up(): void
    {
        Schema::table('booking_travelers', function (Blueprint $table): void {
            $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_name');
            $table->string('eps')->nullable()->after('medical_notes');
            $table->string('eps_other', 120)->nullable()->after('eps');

            // Búsqueda de la planilla por número de documento, siempre dentro del tenant.
            $table->index(['tenant_id', 'document_number']);
        });

        $this->normalizeDocumentTypes();
        $this->backfillEmergencyContactFromBookings();
    }

    public function down(): void
    {
        // WHY: MySQL apoya la FK de `tenant_id` en el índice compuesto y se niega a
        // soltarlo mientras sea el único que empieza por esa columna. Se le devuelve
        // uno propio antes de borrarlo — y solo si no lo tiene ya, para que un
        // segundo `rollback` sobre el mismo esquema no choque con el duplicado.
        if (! Schema::hasIndex('booking_travelers', 'booking_travelers_tenant_id_index')) {
            Schema::table('booking_travelers', function (Blueprint $table): void {
                $table->index('tenant_id');
            });
        }

        Schema::table('booking_travelers', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'document_number']);
            $table->dropColumn(['emergency_contact_relationship', 'eps', 'eps_other']);
        });
    }

    /**
     * `document_type` pasa a castearse como `App\Enums\DocumentType`; cualquier
     * valor previo fuera del enum reventaría al leer el modelo. Los que no
     * pertenecen al catálogo caen en `other`, que es su significado.
     */
    private function normalizeDocumentTypes(): void
    {
        DB::table('booking_travelers')
            ->whereNotNull('document_type')
            ->whereNotIn('document_type', DocumentType::values())
            ->update(['document_type' => DocumentType::Other->value]);
    }

    /**
     * Idempotente por dos lados: solo escribe columnas que estén en `null`, así
     * que nunca pisa un dato ya capturado y correr la migración dos veces no
     * cambia nada. Si no hay snapshots con contacto de emergencia, no hace nada.
     */
    private function backfillEmergencyContactFromBookings(): void
    {
        DB::table('bookings')
            ->select(['id', 'contact_snapshot'])
            ->whereNotNull('contact_snapshot')
            ->orderBy('id')
            ->chunk(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $snapshot = json_decode((string) $booking->contact_snapshot, true);

                    if (! is_array($snapshot)) {
                        continue;
                    }

                    $name = $this->trimmed($snapshot['emergency_contact_name'] ?? null);
                    $phone = $this->trimmed($snapshot['emergency_contact_phone'] ?? null);

                    if ($name === null && $phone === null) {
                        continue;
                    }

                    $traveler = DB::table('booking_travelers')
                        ->where('booking_id', $booking->id)
                        ->orderBy('id')
                        ->first(['id', 'emergency_contact_name', 'emergency_contact_phone']);

                    if ($traveler === null) {
                        continue;
                    }

                    $updates = [];

                    if ($name !== null && $traveler->emergency_contact_name === null) {
                        $updates['emergency_contact_name'] = $name;
                    }

                    if ($phone !== null && $traveler->emergency_contact_phone === null) {
                        $updates['emergency_contact_phone'] = $phone;
                    }

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('booking_travelers')->where('id', $traveler->id)->update($updates);
                }
            });
    }

    private function trimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
};
