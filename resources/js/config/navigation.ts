import {
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    Heart,
    Home,
    LayoutDashboard,
    Mail,
    Megaphone,
    Mountain,
    Settings,
    Star,
    Truck,
    User,
    Users,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { toUrl } from '@/lib/utils';
import { home } from '@/routes';
import {
    bookings as accountBookings,
    favorites as accountFavorites,
    profile as accountProfile,
} from '@/routes/account';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as departuresIndex } from '@/routes/admin/departures';
import { index as logisticsIndex } from '@/routes/admin/logistics';
import { index as newsletterIndex } from '@/routes/admin/newsletter';
import { index as promotionsIndex } from '@/routes/admin/promotions';
import { index as reviewsIndex } from '@/routes/admin/reviews';
import { index as teamIndex } from '@/routes/admin/team';
import { configuration as tenantConfiguration } from '@/routes/admin/tenant';
import { index as toursIndex } from '@/routes/admin/tours';
import { schedule as guideSchedule } from '@/routes/guide';
import type { NavItem, NavSection } from '@/types';
import type { PermissionCheck } from '@/types/auth';

/**
 * Fuente unica del menu de la aplicacion.
 *
 * Antes de F018/Fase 2 la misma regla de visibilidad estaba reescrita a mano en
 * cuatro sitios (`AppSidebar`, `AdminSidebar`, `UserMenuContent`, `PublicLayout`),
 * cada uno con su propio `switch` por rol. Aca se declara una sola vez y por
 * permiso; los cuatro consumidores filtran esta tabla con `useNavigation()`.
 *
 * Regla de oro: si un item aparece, la ruta tiene que responder 200. Todo lo que
 * el backend gobierna con `can:` se declara aca con el mismo permiso.
 */

/**
 * Llave de entrada al panel. `routes/web.php` y `routes/api.php` montan TODO
 * `admin/*` detras de `can:dashboard.view`, asi que el permiso del modulo no
 * alcanza: un `guide` tiene `tours.view` y aun asi recibe 403 en `/admin/tours`
 * (F018 `contracts.md` §1).
 */
export const PANEL_GATE = 'dashboard.view';

/** Permiso que abre la zona de guia (`guide/*`). */
export const GUIDE_GATE = 'guide.schedule.view';

/**
 * Home por rol. Espejo exacto de `App\Services\Auth\RoleHomeResolver`:
 * mismo orden, mismos permisos, mismos destinos.
 */
export const ROLE_HOME = {
    panel: adminDashboard().url,
    guide: guideSchedule().url,
    traveler: home().url,
} as const;

export type PermissionGate = {
    /** Alguno de estos permisos habilita el item. Vacio = sin restriccion. */
    anyOf?: string[];
    /** El item vive bajo `admin/*`: exige ademas el gate de grupo `dashboard.view`. */
    requiresPanel?: boolean;
};

export type NavItemDefinition = NavItem & PermissionGate;

export type NavSectionDefinition = {
    id: string;
    label: string;
    items: NavItemDefinition[];
    /**
     * Zona exclusiva del viajero: se oculta a quien tiene puesto de trabajo.
     * Espejo del middleware `traveler.only` — para el staff, `/account/*`
     * redirige a su home de rol, asi que mostrarlo seria un enlace que rebota.
     */
    travelerOnly?: boolean;
};

export type WorkspaceLink = {
    href: string;
    label: string;
    icon: LucideIcon;
};

const panelSection: NavSectionDefinition = {
    id: 'panel',
    label: 'Administración',
    items: [
        {
            title: 'Dashboard',
            href: ROLE_HOME.panel,
            icon: LayoutDashboard,
            requiresPanel: true,
            anyOf: ['dashboard.view'],
        },
        // A6: "Tours" son los productos (`tours.*`) y "Salidas" las fechas
        // (`departures.*`). Antes el panel llamaba "Productos" a lo primero y
        // "Tours" a lo segundo, mientras el menu del operador usaba "Tours"
        // para lo primero: la misma palabra nombraba dos cosas distintas.
        {
            title: 'Tours',
            href: toursIndex().url,
            icon: Mountain,
            requiresPanel: true,
            anyOf: ['tours.view'],
        },
        {
            title: 'Salidas',
            href: departuresIndex().url,
            icon: CalendarClock,
            requiresPanel: true,
            anyOf: ['departures.view'],
        },
        {
            title: 'Logística',
            href: logisticsIndex().url,
            icon: Truck,
            requiresPanel: true,
            anyOf: ['logistics.view'],
        },
        {
            title: 'Promociones',
            href: promotionsIndex().url,
            icon: Megaphone,
            requiresPanel: true,
            anyOf: ['promotions.view'],
        },
        {
            title: 'Newsletter',
            href: newsletterIndex().url,
            icon: Mail,
            requiresPanel: true,
            anyOf: ['newsletter.view'],
        },
        {
            title: 'Reseñas',
            href: reviewsIndex().url,
            icon: Star,
            requiresPanel: true,
            anyOf: ['reviews.view'],
        },
        {
            title: 'Equipo',
            href: teamIndex().url,
            icon: Users,
            requiresPanel: true,
            anyOf: ['team.view'],
        },
        {
            title: 'Configuración',
            href: tenantConfiguration().url,
            icon: Settings,
            requiresPanel: true,
            anyOf: ['tenant.view'],
        },
    ],
};

const guideSection: NavSectionDefinition = {
    id: 'guide',
    label: 'Guía',
    items: [
        {
            title: 'Mi agenda',
            href: ROLE_HOME.guide,
            icon: CalendarDays,
            anyOf: [GUIDE_GATE],
        },
    ],
};

/**
 * Zona de viajero. Sin permisos propios: el cliente accede a sus reservas y su
 * perfil por propiedad, no por RBAC (`spec.md` F018, user story de `customer`).
 * Pero SI esta cerrada para el staff: `routes/web.php` monta `/account/*` bajo
 * `traveler.only`, que devuelve a quien tenga panel o agenda a su home de rol.
 *
 * El item "Inicio" no esta aca: su destino depende del rol y lo agrega
 * `buildNavSections()` con `resolveHomeUrl()`.
 */
const accountSection: NavSectionDefinition = {
    id: 'account',
    label: 'Mi cuenta',
    travelerOnly: true,
    items: [
        {
            title: 'Mis Reservas',
            href: accountBookings().url,
            icon: CalendarCheck,
        },
        {
            title: 'Favoritos',
            href: accountFavorites().url,
            icon: Heart,
        },
        {
            // `/account` es la pantalla de perfil (`account.profile`); llamarla
            // "Mi Cuenta" dentro de la seccion "Mi cuenta" no decia nada.
            title: 'Mi perfil',
            href: accountProfile().url,
            icon: User,
            exact: true,
        },
    ],
};

/** Orden de aparicion en el sidebar: primero el trabajo, despues lo personal. */
export const navigationSections: NavSectionDefinition[] = [
    panelSection,
    guideSection,
    accountSection,
];

export const PANEL_SECTION_ID = panelSection.id;

export const ACCOUNT_SECTION_ID = accountSection.id;

/** Item "Inicio": el href real lo resuelve `resolveHomeUrl()` segun permisos. */
export const homeNavItem: NavItem = {
    title: 'Inicio',
    href: ROLE_HOME.traveler,
    icon: Home,
    // `/` es prefijo de cualquier URL: sin esto quedaria siempre activo.
    exact: true,
};

/**
 * A donde pertenece este usuario dentro de la agencia. Espejo de
 * `RoleHomeResolver::homeFor()` — el orden importa: `admin` tiene los 38
 * permisos, incluido el de guia, y su casa es el panel.
 */
export function resolveHomeUrl(can: PermissionCheck): string {
    if (can(PANEL_GATE)) {
        return ROLE_HOME.panel;
    }

    if (can(GUIDE_GATE)) {
        return ROLE_HOME.guide;
    }

    return ROLE_HOME.traveler;
}

/**
 * Tiene puesto de trabajo en la agencia (panel o agenda de guia). Espejo de la
 * condicion del middleware `traveler.only`: quien es staff no tiene zona de
 * viajero, todo `/account/*` lo redirige a su home de rol.
 */
export function isStaff(can: PermissionCheck): boolean {
    return resolveHomeUrl(can) !== ROLE_HOME.traveler;
}

/**
 * Enlace de vuelta al puesto de trabajo, para cuando el staff esta navegando
 * fuera del panel (`/settings/*` o el sitio publico). `null` para el cliente,
 * que no tiene otro puesto que el que ya esta viendo.
 */
export function resolveWorkspaceLink(
    can: PermissionCheck,
): WorkspaceLink | null {
    if (can(PANEL_GATE)) {
        return {
            href: ROLE_HOME.panel,
            label: 'Panel de la agencia',
            icon: LayoutDashboard,
        };
    }

    if (can(GUIDE_GATE)) {
        return {
            href: ROLE_HOME.guide,
            label: 'Mi agenda de salidas',
            icon: CalendarDays,
        };
    }

    return null;
}

function isVisible(item: NavItemDefinition, can: PermissionCheck): boolean {
    // El gate de grupo se evalua ANTES del permiso del modulo, igual que en el
    // backend: sin `dashboard.view` toda ruta de `admin/*` responde 403 aunque
    // el usuario tenga el permiso especifico del item (`contracts.md` §1).
    if (item.requiresPanel === true && !can(PANEL_GATE)) {
        return false;
    }

    return can(item.anyOf ?? []);
}

/** Descarta los gates de permiso: al renderer solo le llega lo que pinta. */
function toNavItem(definition: NavItemDefinition): NavItem {
    return {
        title: definition.title,
        href: definition.href,
        icon: definition.icon,
        exact: definition.exact,
    };
}

/**
 * Quita los destinos repetidos entre secciones, quedandose con el primero.
 * "Inicio" apunta al home del rol, que para el staff YA es un item de otra
 * seccion (Dashboard o Mi agenda): sin esta poda el sidebar mostraria dos
 * entradas al mismo sitio, ambas marcadas como activas.
 */
function dedupeByHref(sections: NavSection[]): NavSection[] {
    const seen = new Set<string>();

    return sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => {
                const url = toUrl(item.href);

                if (seen.has(url)) {
                    return false;
                }

                seen.add(url);

                return true;
            }),
        }))
        .filter((section) => section.items.length > 0);
}

/**
 * Menu visible para un juego de permisos dado. Funcion pura: `can` es el unico
 * input, asi que el resultado se puede razonar (y probar) sin montar Vue.
 *
 * @param withHome agrega el item "Inicio" al principio de la zona de cuenta,
 * apuntando al home del rol. El sidebar del panel no lo necesita.
 */
export function buildNavSections(
    can: PermissionCheck,
    withHome: boolean = true,
): NavSection[] {
    const home: NavItem = { ...homeNavItem, href: resolveHomeUrl(can) };
    const staff = isStaff(can);

    const sections = navigationSections
        .filter((section) => !(section.travelerOnly === true && staff))
        .map(
            (section): NavSection => ({
                id: section.id,
                label: section.label,
                items: section.items
                    .filter((item) => isVisible(item, can))
                    .map(toNavItem),
            }),
        )
        .filter((section) => section.items.length > 0)
        .map((section) =>
            withHome && section.id === ACCOUNT_SECTION_ID
                ? { ...section, items: [home, ...section.items] }
                : section,
        );

    return dedupeByHref(sections);
}

/** Solo los items de `admin/*`, para el sidebar del panel. */
export function buildPanelItems(can: PermissionCheck): NavItem[] {
    return (
        buildNavSections(can, false).find(
            (section) => section.id === PANEL_SECTION_ID,
        )?.items ?? []
    );
}
