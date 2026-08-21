/**
 * Identificadores del patrón ARIA de pestañas del tour. Viven acá y no dentro
 * de `TourTabs.vue` porque los usan las dos puntas: la barra (`aria-controls`)
 * y los paneles de la página (`id` + `aria-labelledby`). Una sola convención,
 * o el enlace entre pestaña y panel se rompe en silencio.
 */
export function tourTabId(tab: string): string {
    return `tour-tab-${tab}`;
}

export function tourTabPanelId(tab: string): string {
    return `tour-tabpanel-${tab}`;
}
