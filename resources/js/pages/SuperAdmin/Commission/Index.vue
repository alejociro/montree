<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import Heading from '@/components/Heading.vue';
import CommissionTierEditor from '@/components/organisms/CommissionTierEditor.vue';
import { Button } from '@/components/ui/button';
import { update as updateCommissionSchedule } from '@/routes/super-admin/commission';
import type { CommissionSchedule } from '@/types';

const props = defineProps<{
    schedule: CommissionSchedule;
}>();

const form = useForm<{
    tiers: CommissionSchedule['tiers'];
    max_charge: CommissionSchedule['max_charge'];
}>({
    tiers: props.schedule.tiers,
    max_charge: props.schedule.max_charge,
});

const editor = useTemplateRef<{ hasLiveErrors: boolean }>('editor');

function submit(): void {
    if (editor.value?.hasLiveErrors) {
        return;
    }

    form.put(updateCommissionSchedule.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Super admin · Comisiones')" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Heading
            :title="$t('Comisión de la plataforma')"
            :description="
                $t(
                    'Esquema global por rangos de valor de reserva. Se aplica a toda agencia que no tenga un esquema propio.',
                )
            "
        />

        <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
            <header class="mb-4 space-y-1">
                <h2 class="text-base font-semibold text-foreground">
                    {{ $t('Rangos de comisión') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Moneda :currency. El primer rango empieza en 0 y el último queda siempre abierto.',
                            { currency: schedule.currency },
                        )
                    }}
                </p>
            </header>

            <CommissionTierEditor
                ref="editor"
                v-model="form.tiers"
                v-model:max-charge="form.max_charge"
                :currency="schedule.currency"
                :errors="form.errors"
                :disabled="form.processing"
            />

            <div class="mt-6 flex justify-end">
                <Button :disabled="form.processing" @click="submit">
                    {{
                        form.processing
                            ? $t('Guardando…')
                            : $t('Guardar esquema global')
                    }}
                </Button>
            </div>
        </section>
    </div>
</template>
