import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import type { WorkspaceLink } from '@/config/navigation';
import {
    buildNavSections,
    buildPanelItems,
    isStaff,
    resolveHomeUrl,
    resolveWorkspaceLink,
} from '@/config/navigation';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem, NavItem, NavSection } from '@/types';

export type UseNavigationReturn = {
    /** Menu completo fuera del panel: administracion + guia + cuenta, ya filtrado. */
    sections: ComputedRef<NavSection[]>;
    /** Solo los items de `admin/*`, para el sidebar del panel. */
    panelItems: ComputedRef<NavItem[]>;
    /** Home del rol actual (espejo de `RoleHomeResolver`). */
    homeUrl: ComputedRef<string>;
    /** Vuelta al puesto de trabajo, o `null` para el viajero. */
    workspace: ComputedRef<WorkspaceLink | null>;
    /** Tiene panel o agenda: sin zona de viajero (middleware `traveler.only`). */
    isStaffMember: ComputedRef<boolean>;
    /** Rastro "home / seccion actual" deducido del menu y la URL. */
    breadcrumbs: ComputedRef<BreadcrumbItem[]>;
};

function toCrumb(item: NavItem): BreadcrumbItem {
    return { title: item.title, href: item.href };
}

/**
 * Menu de la aplicacion para el usuario autenticado.
 *
 * No decide nada: envuelve en `computed` el constructor unico y declarativo de
 * `@/config/navigation`, que filtra por permiso. Los cuatro sitios que antes
 * tenian su propio `switch` por rol (`AppSidebar`, `AdminSidebar`,
 * `UserMenuContent`, `PublicLayout`) consumen esto.
 */
export function useNavigation(): UseNavigationReturn {
    const { can } = usePermissions();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const sections = computed(() => buildNavSections(can));
    const homeUrl = computed(() => resolveHomeUrl(can));

    // A9: ninguna pagina de `pages/Admin/` pasa breadcrumbs, asi que la franja
    // superior estaba vacia en todo el panel. El menu ya sabe donde esta el
    // usuario; se reutiliza en vez de repetir el titulo en 12 paginas.
    const breadcrumbs = computed<BreadcrumbItem[]>(() => {
        const items = sections.value.flatMap((section) => section.items);

        // El match mas largo gana: `/admin/tours/create` pertenece a "Tours",
        // no al home, aunque los dos empiecen por `/admin`.
        const current = items
            .filter((item) =>
                item.exact === true
                    ? isCurrentUrl(item.href)
                    : isCurrentOrParentUrl(item.href),
            )
            .sort((a, b) => toUrl(b.href).length - toUrl(a.href).length)[0];

        if (current === undefined) {
            return [];
        }

        const home = items.find((item) => toUrl(item.href) === homeUrl.value);

        if (home === undefined || toUrl(home.href) === toUrl(current.href)) {
            return [toCrumb(current)];
        }

        return [toCrumb(home), toCrumb(current)];
    });

    return {
        sections,
        homeUrl,
        breadcrumbs,
        panelItems: computed(() => buildPanelItems(can)),
        workspace: computed(() => resolveWorkspaceLink(can)),
        isStaffMember: computed(() => isStaff(can)),
    };
}
