<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las tres fichas de logística guardaban cuatro o cinco columnas —nombre,
 * teléfono, una nota— y quien arma una salida necesita el NIT del proveedor,
 * la tarifa negociada del hotel y el desnivel de la ruta. Esta migración lleva
 * las tres al detalle operativo del rediseño y saca a tablas hijas las listas
 * que crecen: paradas, tarifas, documentos y habitaciones.
 *
 * Ninguna columna nueva es obligatoria: las fichas ya cargadas siguen siendo
 * editables sin tener que inventarles un NIT para poder cambiarles el teléfono.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('description');
            $table->string('difficulty')->nullable()->after('kind');
            $table->string('start_point', 500)->nullable()->after('difficulty');
            $table->decimal('start_latitude', 10, 7)->nullable()->after('start_point');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
            $table->string('end_point', 500)->nullable()->after('start_longitude');
            $table->decimal('end_latitude', 10, 7)->nullable()->after('end_point');
            $table->decimal('end_longitude', 10, 7)->nullable()->after('end_latitude');
            $table->string('city')->nullable()->after('end_longitude');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->unsignedSmallInteger('max_altitude_m')->nullable()->after('duration_hours');
            $table->unsignedSmallInteger('elevation_gain_m')->nullable()->after('max_altitude_m');
            $table->unsignedSmallInteger('group_capacity')->nullable()->after('elevation_gain_m');
            $table->json('seasons')->nullable()->after('group_capacity');
            $table->text('safety_notes')->nullable()->after('seasons');
            $table->json('required_gear')->nullable()->after('safety_notes');
            $table->string('permits', 500)->nullable()->after('required_gear');
            $table->string('emergency_contact')->nullable()->after('permits');
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->string('kind');
            $table->string('time_label', 30)->nullable();
            $table->timestamps();

            $table->index(['route_id', 'position']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('service_type');
            $table->string('legal_name')->nullable()->after('description');
            $table->string('tax_id', 40)->nullable()->after('legal_name');
            $table->string('tax_regime')->nullable()->after('tax_id');
            $table->string('billing_email')->nullable()->after('tax_regime');
            $table->string('bank_account')->nullable()->after('billing_email');
            $table->string('payment_terms')->nullable()->after('bank_account');
            $table->string('contact_role')->nullable()->after('contact_name');
            $table->string('alternate_contact')->nullable()->after('contact_email');
            $table->string('service_hours')->nullable()->after('alternate_contact');
            $table->string('address', 500)->nullable()->after('service_hours');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city')->nullable()->after('longitude');
            $table->string('state')->nullable()->after('city');
            $table->string('coverage')->nullable()->after('state');
            $table->string('currency', 3)->nullable()->after('coverage');
            $table->date('rates_valid_until')->nullable()->after('currency');
        });

        Schema::create('provider_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('concept');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('unit');
            $table->timestamps();

            $table->index(['provider_id', 'position']);
        });

        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind');
            $table->string('number', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'position']);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->string('accommodation_type')->nullable()->after('name');
            $table->unsignedTinyInteger('star_rating')->nullable()->after('accommodation_type');
            $table->text('description')->nullable()->after('star_rating');
            $table->string('legal_name')->nullable()->after('description');
            $table->string('tax_id', 40)->nullable()->after('legal_name');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city')->nullable()->after('longitude');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->text('directions')->nullable()->after('country');
            $table->unsignedSmallInteger('total_capacity')->nullable()->after('directions');
            $table->string('currency', 3)->nullable()->after('total_capacity');
            $table->date('rates_valid_until')->nullable()->after('currency');
            $table->string('check_in', 30)->nullable()->after('rates_valid_until');
            $table->string('check_out', 30)->nullable()->after('check_in');
            $table->json('amenities')->nullable()->after('check_out');
            $table->string('meal_plan')->nullable()->after('amenities');
            $table->string('diets')->nullable()->after('meal_plan');
            $table->text('restrictions')->nullable()->after('diets');
            $table->string('contact_name')->nullable()->after('restrictions');
            $table->string('emergency_contact')->nullable()->after('contact_email');
            $table->string('cancellation_policy')->nullable()->after('emergency_contact');
            $table->string('payment_terms')->nullable()->after('cancellation_policy');
        });

        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->decimal('nightly_rate', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'position']);
        });

        $this->normalizeProviderServiceTypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rooms');
        Schema::dropIfExists('provider_documents');
        Schema::dropIfExists('provider_rates');
        Schema::dropIfExists('route_stops');

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'accommodation_type', 'star_rating', 'description', 'legal_name', 'tax_id',
                'latitude', 'longitude', 'city', 'state', 'country', 'directions',
                'total_capacity', 'currency', 'rates_valid_until', 'check_in', 'check_out',
                'amenities', 'meal_plan', 'diets', 'restrictions', 'contact_name',
                'emergency_contact', 'cancellation_policy', 'payment_terms',
            ]);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'legal_name', 'tax_id', 'tax_regime', 'billing_email',
                'bank_account', 'payment_terms', 'contact_role', 'alternate_contact',
                'service_hours', 'address', 'latitude', 'longitude', 'city', 'state',
                'coverage', 'currency', 'rates_valid_until',
            ]);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'kind', 'difficulty', 'start_point', 'start_latitude', 'start_longitude',
                'end_point', 'end_latitude', 'end_longitude', 'city', 'state', 'country',
                'max_altitude_m', 'elevation_gain_m', 'group_capacity', 'seasons',
                'safety_notes', 'required_gear', 'permits', 'emergency_contact',
            ]);
        });
    }

    /**
     * `service_type` era texto libre y ahora es un enum. Lo que no case con
     * ninguna de las seis opciones cae en «Otro» en vez de perderse: la
     * descripción original queda en `description` para no borrar información.
     */
    private function normalizeProviderServiceTypes(): void
    {
        $map = [
            'transporte' => 'transport', 'transport' => 'transport', 'bus' => 'transport',
            'alimentación' => 'food', 'alimentacion' => 'food', 'comida' => 'food', 'food' => 'food',
            'guianza' => 'guiding', 'guía' => 'guiding', 'guia' => 'guiding', 'guiding' => 'guiding',
            'actividades' => 'activities', 'activities' => 'activities',
            'equipo' => 'equipment', 'equipos' => 'equipment', 'equipment' => 'equipment',
        ];

        DB::table('providers')
            ->whereNotNull('service_type')
            ->select(['id', 'service_type'])
            ->chunkById(200, function ($providers) use ($map): void {
                foreach ($providers as $provider) {
                    $raw = mb_strtolower(trim((string) $provider->service_type));
                    $mapped = $map[$raw] ?? 'other';

                    DB::table('providers')->where('id', $provider->id)->update([
                        'service_type' => $mapped,
                        'description' => $mapped === 'other' && $raw !== '' ? $provider->service_type : null,
                    ]);
                }
            });
    }
};
