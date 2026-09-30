<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, useTemplateRef } from 'vue';
import CommissionTierEditor from '@/components/organisms/CommissionTierEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updateCommission } from '@/routes/super-admin/tenants/commission';
import type { CommissionSchedule, CommissionScope } from '@/types';

const GLOBAL = 'global';
const TENANT = 'tenant';

const props = defineProps<{
    tenantId: number;
    /** Esquema EFECTIVO hoy: el propio si existe, si no el global. */
    schedule: CommissionSchedule & { scope: CommissionScope };
}>();

const form = useForm<{
    use_global: CommissionScope;
    tiers: CommissionSchedule['tiers'];
    max_charge: CommissionSchedule['max_charge'];
}>({
    use_global: props.schedule.scope,
    tiers: props.schedule.tiers,
    max_charge: props.schedule.max_charge,
});

const usingOwn = computed(() => form.use_global === TENANT);

const editor = useTemplateRef<{ hasLiveErrors: boolean }>('editor');

function submit(): void {
    if (usingOwn.value && editor.value?.hasLiveErrors) {
        return;
    }

    form.transform((data) => ({
        use_global: data.use_global === GLOBAL,
        tiers: data.use_global === TENANT ? data.tiers : undefined,
        max_charge: data.use_global === TENANT ? data.max_charge : undefined,
    })).put(updateCommission.url(props.tenantId), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
        <header class="mb-4 space-y-1">
            <h2 class="text-base font-semibold text-foreground">
                {{ $t('Cobro de la plataforma') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Se genera un cargo por cada reserva que se confirma. Los cargos ya emitidos no se recalculan.',
                    )
                }}
            </p>
        </header>

        <div class="mb-4 space-y-2">
            <Select v-model="form.use_global">
                <SelectTrigger
                    class="w-56"
                    :aria-label="$t('Esquema de comisión')"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="GLOBAL">{{
                        $t('Usar esquema global')
                    }}</SelectItem>
                    <SelectItem :value="TENANT">{{
                        $t('Esquema propio')
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <CommissionTierEditor
            v-if="usingOwn"
            ref="editor"
            v-model="form.tiers"
            v-model:max-charge="form.max_charge"
            :currency="schedule.currency"
            :errors="form.errors"
            :disabled="form.processing"
        />
        <p v-else class="text-sm text-muted-foreground">
            {{ $t('Esta agencia usa el esquema global vigente.') }}
        </p>

        <div class="mt-4 flex justify-end">
            <Button type="button" :disabled="form.processing" @click="submit">
                {{ form.processing ? $t('Guardando…') : $t('Guardar cobro') }}
            </Button>
        </div>
    </section>
</template>
