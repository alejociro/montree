/**
 * Roles y permisos administrables por la agencia (F018 Fase 3B).
 *
 * Dos familias de rol conviven:
 * - **base** (`is_base: true`): los 6 roles del sistema. Son filas globales,
 *   compartidas por todos los tenants, asi que la agencia los ve pero no los
 *   edita (decision (a) de `planfase.md` Fase 3B).
 * - **propios** (`is_base: false`): roles creados por la agencia, con
 *   `tenant_id`. Editables y borrables.
 */

/** Un permiso del catalogo, tal como lo devuelve `PermissionCatalog`. */
export interface PermissionSummary {
    /** Identificador `modulo.accion`, p. ej. `tours.publish`. */
    slug: string;
    /** Modulo al que pertenece (`tours`, `bookings`, ...). Agrupa el picker. */
    module: string;
    /** Etiqueta del modulo. La manda el backend; si falta, la pone el front. */
    module_label?: string;
    /** Etiqueta legible del permiso. */
    label: string;
}

/**
 * Rol tal como viaja dentro de otro recurso (`RoleSummaryResource`): es el shape
 * que trae cada miembro del equipo en `roles`.
 */
export interface RoleSummary {
    id: number;
    name: string;
    label: string;
    is_base: boolean;
}

/** Respuesta de `GET /api/v1/admin/roles`: los roles y el catálogo completo. */
export interface RoleListResponse {
    data: RoleListItem[];
    meta?: {
        /** Los 38 permisos con módulo y etiqueta, para el selector. */
        available_permissions?: PermissionSummary[];
    };
}

/** Fila del listado `GET /admin/roles`. */
export interface RoleListItem {
    id: number;
    /** Nombre tecnico (`admin`, `ventas-fin-de-semana`). */
    name: string;
    /** Etiqueta para mostrar. */
    label: string;
    is_base: boolean;
    permissions_count: number;
    users_count: number;
}

/** Detalle `GET /admin/roles/{role}`: el rol con sus permisos. */
export interface RoleDetail {
    id: number;
    name: string;
    label: string;
    is_base: boolean;
    permissions: PermissionSummary[];
}

/** Cuerpo de `POST /admin/roles` y `PATCH /admin/roles/{role}`. */
export interface RoleFormInput {
    name: string;
    permissions: string[];
}

/** Catalogo agrupado que consume el selector de permisos. */
export interface PermissionModule {
    /** Clave del modulo (`tours`), la misma que trae cada permiso. */
    key: string;
    /** Titulo del grupo en la UI ("Productos"). */
    label: string;
    permissions: PermissionSummary[];
}
