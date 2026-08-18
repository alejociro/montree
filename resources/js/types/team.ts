import type { PaginationLinks, PaginationMeta } from './pagination';
import type { RoleSummary } from './role';

/** Estado de la membresia del usuario en el tenant (`TenantMembershipStatus`). */
export type TeamMemberStatus = 'active' | 'invited' | 'suspended';

/**
 * Miembro del equipo ya normalizado para la UI.
 *
 * Desde F018 Fase 3A un miembro tiene **varios** roles: `roles` son los nombres
 * tecnicos que se envian al backend y `roleLabels` lo que se muestra.
 */
export interface TeamMember {
    id: number;
    name: string;
    email: string;
    roles: string[];
    roleLabels: RoleOption[];
    status: TeamMemberStatus;
    joined_at: string | null;
    /** `null` = nunca inicio sesion. */
    last_login_at: string | null;
}

/**
 * Item crudo del listado (`TeamMemberResource`). Desde F018 Fase 3A `roles` es
 * una lista de objetos (`RoleSummaryResource`); el campo `role` singular ya no
 * existe en la respuesta.
 */
export interface TeamMemberPayload {
    id: number;
    name: string;
    email: string;
    roles: RoleSummary[];
    status: string;
    invited_at?: string | null;
    joined_at?: string | null;
    last_login_at?: string | null;
}

export interface TeamListResponse {
    data: TeamMemberPayload[];
    links?: PaginationLinks;
    meta?: PaginationMeta;
}

/** Filtros del listado, espejo de los query params del endpoint. */
export interface TeamFilters {
    search: string;
    /** `all` = sin filtro. */
    status: TeamMemberStatus | 'all';
    /** Nombre tecnico del rol, o `all`. */
    role: string;
}

/** Opcion asignable en el selector de roles. */
export interface RoleOption {
    name: string;
    label: string;
}
