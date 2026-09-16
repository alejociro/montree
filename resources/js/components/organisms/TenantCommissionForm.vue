<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updateCommission } from '@/routes/super-admin/tenants/commission';
import type { CommissionType, TenantCommission } from '@/types';

const NONE = 'none';

const props = defineProps<{
    tenantId: number;
    commission: TenantCommission;
}>();

const form = useForm<{ type: CommissionType | typeof NONE; value: string }>({
    type: props.commission.type ?? NONE,
    value: props.commission.value ?? '',
});

function submit(): void {
    form.transform((data) => ({
        type: data.type === NONE ? null : data.type,
        value: data.type === NONE ? null : data.value,
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

        <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-2">
                <Label>{{ $t('Tipo de cobro') }}</Label>
                <Select v-model="form.type">
                    <SelectTrigger>
                        <SelectValue :placeholder="$t('Seleccionar tipo')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">{{
                            $t('Sin cobro')
                        }}</SelectItem>
                        <SelectItem value="percentage">{{
                            $t('Porcentaje por reserva')
                        }}</SelectItem>
                        <SelectItem value="fixed">{{
                            $t('Monto fijo por reserva')
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.errors.type" class="text-xs text-destructive">
                    {{ form.errors.type }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="commission-value">
                    {{
                        form.type === 'percentage'
                            ? $t('Porcentaje (%)')
                            : $t('Monto (:currency)', {
                                  currency: commission.currency,
                              })
                    }}
                </Label>
                <Input
                    id="commission-value"
                    v-model="form.value"
                    type="number"
                    min="0"
                    step="0.01"
                    :disabled="form.type === 'none'"
                />
                <p v-if="form.errors.value" class="text-xs text-destructive">
                    {{ form.errors.value }}
                </p>
            </div>

            <div class="flex items-end">
                <Button type="submit" :disabled="form.processing">
                    {{
                        form.processing
                            ? $t('Guardando…')
                            : $t('Guardar cobro')
                    }}
                </Button>
            </div>
        </form>
    </section>
</template>
