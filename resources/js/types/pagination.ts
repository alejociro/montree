/**
 * Shape estandar de `LengthAwarePaginator` de Laravel (`data` / `links` / `meta`).
 *
 * Vivia dentro de `types/logistics.ts` porque el primer listado paginado del
 * panel fue el de salidas; desde F018 Fase 3 lo consume tambien el listado de
 * equipo, asi que se extrajo aca. `types/logistics.ts` lo reexporta para no
 * romper los imports existentes.
 */
export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    links?: PaginationLinks;
    meta?: PaginationMeta;
}
