import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import type { LogisticsRef } from '@/types/logistics';

/** Recorte de `TeamMemberResource`: `roles` viaja como objetos. */
type TeamMember = { id: number; name: string; roles: { name: string }[] };

export type UseTenantGuidesReturn = {
    guides: Ref<LogisticsRef[]>;
    loading: Ref<boolean>;
};

/**
 * Los guías del equipo del tenant.
 *
 * WHY: el guía por defecto del tour (D7) no tiene fechas, así que
 * `useGuideAvailability` —que responde «quién está libre entre el día X y el
 * Y»— no aplica: sin rango no hay agenda que consultar. Acá solo hace falta la
 * lista, y sale del mismo equipo que alimenta el select de cada salida.
 */
export function useTenantGuides(): UseTenantGuidesReturn {
    const guides = ref<LogisticsRef[]>([]);
    const loading = ref(false);

    onMounted(async () => {
        loading.value = true;

        try {
            const response = await fetch(teamIndex().url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as { data: TeamMember[] };

            guides.value = payload.data
                .filter((member) =>
                    (member.roles ?? []).some((role) => role.name === 'guide'),
                )
                .map((member) => ({ id: member.id, name: member.name }));
        } catch {
            // El servidor sigue validando `default_guide_id`: sin lista, el
            // campo queda vacío y el checklist de publicación lo señala.
            guides.value = [];
        } finally {
            loading.value = false;
        }
    });

    return { guides, loading };
}
