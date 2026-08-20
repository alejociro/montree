<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Presentación del catálogo cerrado de 39 permisos: a qué módulo pertenece cada uno
 * y cómo se llama en la UI.
 *
 * WHY: la lista y el agrupamiento NO se reescriben acá — se leen de
 * `RolesAndPermissionsSeeder::PERMISSIONS`, que es la fuente de verdad (F018 spec.md).
 * Este servicio solo agrega las etiquetas, que son presentación y no van en un seeder.
 */
final class PermissionCatalog
{
    /**
     * @var array<string, string>
     */
    private const MODULE_LABELS = [
        'dashboard' => 'Panel',
        'tours' => 'Tours',
        'departures' => 'Salidas',
        'logistics' => 'Logística',
        'bookings' => 'Reservas',
        'promotions' => 'Promociones',
        'newsletter' => 'Newsletter',
        'reviews' => 'Reseñas',
        'team' => 'Equipo',
        'tenant' => 'Agencia',
        'guide' => 'Guía',
    ];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'dashboard.view' => 'Ver el panel',
        'reports.view' => 'Ver reportes',
        'reports.export' => 'Exportar reportes',
        'tours.view' => 'Ver tours',
        'tours.create' => 'Crear tours',
        'tours.update' => 'Editar tours',
        'tours.publish' => 'Publicar tours',
        'tours.delete' => 'Eliminar tours',
        'tours.images.manage' => 'Gestionar imágenes de tours',
        'departures.view' => 'Ver salidas',
        'departures.create' => 'Crear salidas',
        'departures.update' => 'Editar salidas',
        'departures.cancel' => 'Cancelar salidas',
        'departures.delete' => 'Eliminar salidas',
        'departures.assign_guide' => 'Asignar guía a una salida',
        'logistics.view' => 'Ver logística',
        'logistics.manage' => 'Gestionar logística',
        'bookings.view' => 'Ver reservas',
        'bookings.update' => 'Editar reservas',
        'bookings.passengers.medical.view' => 'Ver EPS y observaciones médicas de los pasajeros',
        'payments.refund' => 'Reembolsar pagos',
        'promotions.view' => 'Ver promociones',
        'promotions.create' => 'Crear promociones',
        'promotions.update' => 'Editar promociones',
        'promotions.delete' => 'Eliminar promociones',
        'newsletter.view' => 'Ver suscriptores',
        'newsletter.send' => 'Enviar campañas',
        'reviews.view' => 'Ver reseñas',
        'reviews.moderate' => 'Moderar reseñas',
        'reviews.respond' => 'Responder reseñas',
        'team.view' => 'Ver el equipo',
        'team.invite' => 'Invitar miembros',
        'team.role.update' => 'Gestionar roles y permisos',
        'team.suspend' => 'Suspender miembros',
        'tenant.view' => 'Ver la configuración de la agencia',
        'tenant.update' => 'Editar los datos de la agencia',
        'tenant.settings.update' => 'Editar la configuración de la agencia',
        'guide.schedule.view' => 'Ver la agenda del guía',
        'guide.travelers.view' => 'Ver los viajeros de sus salidas',
    ];

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return RolesAndPermissionsSeeder::permissionNames();
    }

    /**
     * @return array<int, array{slug: string, module: string, module_label: string, label: string}>
     */
    public function all(): array
    {
        $catalog = [];

        foreach (RolesAndPermissionsSeeder::PERMISSIONS as $module => $slugs) {
            foreach ($slugs as $slug) {
                $catalog[] = $this->entry($slug, $module);
            }
        }

        return $catalog;
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, array{slug: string, module: string, module_label: string, label: string}>
     */
    public function describe(array $slugs): array
    {
        $wanted = array_flip($slugs);

        return array_values(array_filter(
            $this->all(),
            fn (array $permission): bool => isset($wanted[$permission['slug']]),
        ));
    }

    public function labelFor(string $slug): string
    {
        return self::LABELS[$slug] ?? $slug;
    }

    /**
     * @return array{slug: string, module: string, module_label: string, label: string}
     */
    private function entry(string $slug, string $module): array
    {
        return [
            'slug' => $slug,
            'module' => $module,
            'module_label' => self::MODULE_LABELS[$module] ?? $module,
            'label' => $this->labelFor($slug),
        ];
    }
}
