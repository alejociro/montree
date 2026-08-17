import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /**
     * Marca el item como activo solo en su URL exacta, no en las hijas.
     * Necesario para destinos que son prefijo de todo lo demas (`/`) o de
     * sus hermanos (`/account` respecto de `/account/bookings`).
     */
    exact?: boolean;
};

/** Grupo de items del sidebar, con su etiqueta de seccion. */
export type NavSection = {
    id: string;
    label: string;
    items: NavItem[];
};
