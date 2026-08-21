import type { PermissionModule, PermissionSummary } from '@/types/role';

/**
 * Catalogo de los 39 permisos de F018, agrupado por modulo.
 *
 * **No es la fuente de verdad**: el catalogo real llega del backend en
 * `GET /api/v1/admin/roles` → `meta.available_permissions`
 * (`App\Services\Rbac\PermissionCatalog`), y es el que consume el selector. Esta
 * tabla es el espejo de `RolesAndPermissionsSeeder::PERMISSIONS` que se usa como
 * respaldo cuando esa respuesta no trae el catalogo, y el orden de modulos con
 * el que se agrupa cualquier lista de permisos.
 *
 * Si el backend agrega un permiso y esta tabla no se actualiza, no se pierde:
 * `groupPermissions()` lo muestra igual, bajo el modulo que declare el backend.
 */

/**
 * Etiqueta visible de cada modulo y orden del picker. Espejo de
 * `PermissionCatalog::MODULE_LABELS`; cuando el backend manda `module_label` en
 * el permiso, manda el backend.
 */
export const PERMISSION_MODULE_LABELS: Record<string, string> = {
    dashboard: 'Panel',
    tours: 'Tours',
    departures: 'Salidas',
    logistics: 'Logística',
    bookings: 'Reservas',
    promotions: 'Promociones',
    newsletter: 'Newsletter',
    reviews: 'Reseñas',
    team: 'Equipo',
    tenant: 'Agencia',
    guide: 'Guía',
};

const CATALOG_BY_MODULE: Record<string, Array<[string, string]>> = {
    dashboard: [
        ['dashboard.view', 'Ver el panel'],
        ['reports.view', 'Ver reportes'],
        ['reports.export', 'Exportar reportes a CSV'],
    ],
    tours: [
        ['tours.view', 'Ver productos'],
        ['tours.create', 'Crear productos'],
        ['tours.update', 'Editar productos'],
        ['tours.publish', 'Publicar y despublicar'],
        ['tours.delete', 'Archivar y eliminar'],
        ['tours.images.manage', 'Gestionar imágenes'],
    ],
    departures: [
        ['departures.view', 'Ver salidas'],
        ['departures.create', 'Programar salidas'],
        ['departures.update', 'Editar salidas'],
        ['departures.cancel', 'Cancelar salidas'],
        ['departures.delete', 'Eliminar salidas'],
        ['departures.assign_guide', 'Asignar guía'],
    ],
    logistics: [
        ['logistics.view', 'Ver logística'],
        ['logistics.manage', 'Gestionar rutas, proveedores y hoteles'],
    ],
    bookings: [
        ['bookings.view', 'Ver reservas'],
        ['bookings.update', 'Editar reservas'],
        ['bookings.passengers.medical.view', 'Ver EPS y observaciones médicas'],
        ['payments.refund', 'Emitir reembolsos'],
    ],
    promotions: [
        ['promotions.view', 'Ver promociones'],
        ['promotions.create', 'Crear promociones'],
        ['promotions.update', 'Editar promociones'],
        ['promotions.delete', 'Eliminar promociones'],
    ],
    newsletter: [
        ['newsletter.view', 'Ver suscriptores'],
        ['newsletter.send', 'Enviar campañas'],
    ],
    reviews: [
        ['reviews.view', 'Ver reseñas'],
        ['reviews.moderate', 'Aprobar y rechazar reseñas'],
        ['reviews.respond', 'Responder reseñas'],
    ],
    team: [
        ['team.view', 'Ver el equipo'],
        ['team.invite', 'Invitar miembros'],
        ['team.role.update', 'Cambiar roles y permisos'],
        ['team.suspend', 'Suspender y reactivar miembros'],
    ],
    tenant: [
        ['tenant.view', 'Ver la configuración de la agencia'],
        ['tenant.update', 'Editar los datos de la agencia'],
        ['tenant.settings.update', 'Editar la configuración operativa'],
    ],
    guide: [
        ['guide.schedule.view', 'Ver la agenda de salidas'],
        ['guide.travelers.view', 'Ver los viajeros de una salida'],
    ],
};

/** Los 39 permisos como lista plana, en el orden del catalogo. */
export const PERMISSION_CATALOG: PermissionSummary[] = Object.entries(
    CATALOG_BY_MODULE,
).flatMap(([module, entries]) =>
    entries.map(([slug, label]) => ({ slug, module, label })),
);

const CATALOG_BY_SLUG = new Map(
    PERMISSION_CATALOG.map((permission) => [permission.slug, permission]),
);

/** Modulo al que pertenece un slug desconocido: su prefijo (`tours.create`). */
function fallbackModule(slug: string): string {
    return slug.split('.')[0] ?? 'otros';
}

/**
 * Metadatos de un permiso. Si no esta en el catalogo (backend adelantado a esta
 * tabla) se devuelve el slug como etiqueta, en vez de esconderlo.
 */
export function describePermission(slug: string): PermissionSummary {
    return (
        CATALOG_BY_SLUG.get(slug) ?? {
            slug,
            module: fallbackModule(slug),
            label: slug,
        }
    );
}

export function permissionModuleLabel(module: string): string {
    return PERMISSION_MODULE_LABELS[module] ?? module;
}

/**
 * Agrupa permisos por modulo respetando el orden del catalogo; los modulos que
 * el catalogo no conoce van al final, en el orden en que aparecen.
 */
export function groupPermissions(
    permissions: PermissionSummary[],
): PermissionModule[] {
    const groups = new Map<string, PermissionSummary[]>();
    // La etiqueta del modulo la manda el backend con el permiso; la tabla local
    // solo se usa para lo que no venga rotulado.
    const labels = new Map<string, string>();

    for (const permission of permissions) {
        const module =
            permission.module !== ''
                ? permission.module
                : fallbackModule(permission.slug);

        if (permission.module_label !== undefined && !labels.has(module)) {
            labels.set(module, permission.module_label);
        }

        const bucket = groups.get(module);

        if (bucket) {
            bucket.push(permission);
            continue;
        }

        groups.set(module, [permission]);
    }

    const known = Object.keys(PERMISSION_MODULE_LABELS).filter((module) =>
        groups.has(module),
    );
    const unknown = [...groups.keys()].filter(
        (module) => !(module in PERMISSION_MODULE_LABELS),
    );

    return [...known, ...unknown].map((module) => ({
        key: module,
        label: labels.get(module) ?? permissionModuleLabel(module),
        permissions: groups.get(module) ?? [],
    }));
}
