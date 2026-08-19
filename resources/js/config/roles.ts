import type { RoleOption } from '@/types/team';

/**
 * Etiquetas de los roles base. Espejo de `App\Enums\UserRole::label()`.
 *
 * Solo cubre los roles del sistema: los roles propios de la agencia (F018 Fase
 * 3B) traen su etiqueta del backend, y para ellos `roleLabel()` cae en el
 * nombre tecnico tal cual lo escribio quien creo el rol.
 */
export const BASE_ROLE_LABELS: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Administrador',
    sales: 'Vendedor',
    operator: 'Operador',
    guide: 'Guía',
    customer: 'Viajero',
};

/**
 * Roles que el panel de una agencia nunca asigna: `super_admin` es global de la
 * plataforma y `customer` no es un puesto de trabajo. Espejo de
 * `TenantRoleCatalog::STAFF_ROLES`, que es lo que el backend acepta.
 */
export const NON_ASSIGNABLE_ROLES: string[] = ['super_admin', 'customer'];

/**
 * Roles asignables cuando el listado de `/admin/roles` no esta disponible (sin
 * permiso `team.role.update`). Mismo juego que `TenantRoleCatalog::STAFF_ROLES`,
 * sin los roles propios de la agencia, que solo se conocen consultando.
 */
export const FALLBACK_ROLE_OPTIONS: RoleOption[] = [
    'admin',
    'sales',
    'operator',
    'guide',
].map((name) => ({ name, label: BASE_ROLE_LABELS[name] ?? name }));

export function roleLabel(name: string): string {
    return BASE_ROLE_LABELS[name] ?? name;
}

/** Etiqueta de una lista de roles: "Administrador · Vendedor". */
export function roleLabels(names: string[], labels?: RoleOption[]): string {
    if (names.length === 0) {
        return 'Sin rol';
    }

    return names
        .map(
            (name) =>
                labels?.find((option) => option.name === name)?.label ??
                roleLabel(name),
        )
        .join(' · ');
}
