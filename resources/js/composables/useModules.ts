import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { Module } from '@/types/enums.generated';

export type ModuleFlags = Record<Module, boolean>;

/**
 * Modulos del producto que estan encendidos, tal como los comparte
 * `HandleInertiaRequests`. Espejo exacto de `App\Enums\Module`.
 *
 * Apagado no es "sin permiso": la ruta responde 404 y el permiso ni siquiera
 * esta en el catalogo, asi que el menu y las pantallas tienen que preguntar por
 * el modulo ADEMAS de por el permiso.
 */
export function useModules(): {
    modules: ComputedRef<ModuleFlags>;
    isModuleEnabled: (key: string) => boolean;
} {
    const page = usePage();
    const modules = computed(() => page.props.modules);

    return {
        modules,
        isModuleEnabled: (key: string): boolean => isModuleEnabled(modules.value, key),
    };
}

/**
 * Una clave que no es un modulo desactivable siempre esta encendida: el gate
 * solo conoce `newsletter` y `promotions`, y todo lo demas es el producto base.
 */
export function isModuleEnabled(modules: ModuleFlags, key: string): boolean {
    return modules[key as Module] ?? true;
}
