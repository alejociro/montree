import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { PermissionCheck } from '@/types/auth';

export type UsePermissionsReturn = {
    permissions: ComputedRef<string[]>;
    can: PermissionCheck;
    canAll: (permissions: string[]) => boolean;
};

/**
 * Permisos efectivos del usuario autenticado, tal como los comparte
 * `HandleInertiaRequests` (F018 `contracts.md` §2).
 *
 * Solo sirve para decidir que se muestra: la autorizacion real vive en el
 * backend (`can:` por ruta + Policies). Si el backend y este helper alguna vez
 * discrepan, manda el backend y el usuario ve un 403.
 *
 * Se lee `auth.permissions` (y no `auth.user.permissions`) porque es la prop
 * que existe siempre — para un invitado llega como array vacio, mientras que
 * `auth.user` es `null`.
 */
export function usePermissions(): UsePermissionsReturn {
    const page = usePage();

    const permissions = computed<string[]>(
        () => page.props.auth?.permissions ?? [],
    );

    function can(permission: string | string[]): boolean {
        const wanted = Array.isArray(permission) ? permission : [permission];

        // Sin permisos declarados el item no esta restringido.
        if (wanted.length === 0) {
            return true;
        }

        return wanted.some((name) => permissions.value.includes(name));
    }

    function canAll(wanted: string[]): boolean {
        return wanted.every((name) => permissions.value.includes(name));
    }

    return { permissions, can, canAll };
}
