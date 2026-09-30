<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { CommissionTier } from '@/types';

const MAX_TIERS = 10;

const props = defineProps<{
    modelValue: CommissionTier[];
    currency: string;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [tiers: CommissionTier[]];
}>();

const maxCharge = defineModel<string | null>('maxCharge', { default: null });

/**
 * Cada fila deja editar el "hasta" (salvo la última, siempre abierta) y el
 * porcentaje/tope; el "desde" se calcula solo a partir del "hasta" anterior.
 * Así la UI no puede producir huecos ni solapes: la contigüidad es
 * estructural, no una regla que el usuario pueda romper.
 */
const rows = computed(() => props.modelValue);

const { t } = useTranslations();

/**
 * Validación en vivo: los mismos límites que `ValidCommissionTiers`, para que el
 * super admin vea el problema en la fila mientras escribe y no solo al guardar.
 */
const liveErrors = computed<Record<string, string>>(() => {
    const found: Record<string, string> = {};

    rows.value.forEach((tier, index) => {
        const position = index + 1;
        const from = Number.parseFloat(tier.from);
        const isLast = index === rows.value.length - 1;

        if (!isLast) {
            const to = Number.parseFloat(tier.to ?? '');

            if (!Number.isFinite(to) || to <= from) {
                found[`tiers.${index}.to`] = t(
                    'El rango :position debe terminar después de donde empieza.',
                    { position },
                );
            }
        }

        const rate = Number.parseFloat(tier.rate);

        if (!Number.isFinite(rate) || rate < 0 || rate > 100) {
            found[`tiers.${index}.rate`] = t(
                'El porcentaje del rango :position debe estar entre 0 y 100.',
                { position },
            );
        }
    });

    if (maxCharge.value !== null && maxCharge.value !== '') {
        const parsedMaxCharge = Number.parseFloat(maxCharge.value);

        if (!Number.isFinite(parsedMaxCharge) || parsedMaxCharge <= 0) {
            found['max_charge'] = t(
                'El tope máximo por reserva debe ser mayor que 0.',
            );
        }
    }

    return found;
});

const hasLiveErrors = computed(() => Object.keys(liveErrors.value).length > 0);

defineExpose({ hasLiveErrors });

// WHY: la regla del servidor reporta sobre `tiers` (huecos, solapes, formato);
// sin mostrarla, un rechazo del backend quedaba invisible.
const scheduleError = computed(() => props.errors?.tiers);

function errorFor(index: number, field: string): string | undefined {
    const key = `tiers.${index}.${field}`;

    return liveErrors.value[key] ?? props.errors?.[key];
}

// WHY: separadas para que la plantilla no interpole literales ('to', 'rate',
// 'cap') dentro de `{{ }}` — el chequeo de textos crudos del catálogo de
// traducción no distingue una clave de campo de una copia sin traducir.
function toErrorFor(index: number): string | undefined {
    return errorFor(index, 'to');
}

function rateErrorFor(index: number): string | undefined {
    return errorFor(index, 'rate');
}

const maxChargeError = computed(
    () => liveErrors.value['max_charge'] ?? props.errors?.max_charge,
);

function emitTiers(next: CommissionTier[]): void {
    // WHY: recalcula `from` desde `to` del anterior, así una edición de
    // "hasta" nunca deja rangos inconsistentes.
    const normalized = next.map((tier, index) => ({
        ...tier,
        from: index === 0 ? '0' : (next[index - 1].to ?? '0'),
    }));

    emit('update:modelValue', normalized);
}

function updateTo(index: number, value: string): void {
    const next = rows.value.map((tier, i) =>
        i === index ? { ...tier, to: value === '' ? null : value } : tier,
    );
    emitTiers(next);
}

function updateRate(index: number, value: string): void {
    const next = rows.value.map((tier, i) =>
        i === index ? { ...tier, rate: value } : tier,
    );
    emitTiers(next);
}

function addTier(): void {
    if (rows.value.length >= MAX_TIERS) {
        return;
    }

    const last = rows.value[rows.value.length - 1];
    // WHY: el nuevo rango se abre a la mitad del último (que estaba abierto),
    // cerrándolo con un "hasta" razonable: el doble de su "desde", o 100 si
    // arrancaba en 0. El super admin lo ajusta después.
    const splitPoint =
        last.from === '0' ? '100' : String(Number(last.from) * 2);

    const next = [
        ...rows.value.slice(0, -1),
        { ...last, to: splitPoint },
        { from: splitPoint, to: null, rate: last.rate },
    ];

    emitTiers(next);
}

function removeTier(index: number): void {
    if (rows.value.length <= 1) {
        return;
    }

    const next = rows.value.filter((_, i) => i !== index);
    // El último rango del resultado siempre queda abierto.
    next[next.length - 1] = { ...next[next.length - 1], to: null };

    emitTiers(next);
}

const exampleAmount = defineModel<string>('exampleAmount', { default: '' });

const exampleResult = computed(() => {
    const amount = Number.parseFloat(exampleAmount.value);

    if (!Number.isFinite(amount) || amount < 0) {
        return null;
    }

    const cap =
        maxCharge.value === null || maxCharge.value === ''
            ? null
            : Number.parseFloat(maxCharge.value);

    for (const tier of rows.value) {
        const from = Number.parseFloat(tier.from);
        const to = tier.to === null ? null : Number.parseFloat(tier.to);
        const rate = Number.parseFloat(tier.rate);

        if (amount >= from && (to === null || amount < to)) {
            const raw = (amount * rate) / 100;
            const capped = cap !== null && raw > cap;

            return {
                amount: capped ? cap! : raw,
                rate,
                capped,
            };
        }
    }

    return null;
});
</script>

<template>
    <div class="space-y-4">
        <p
            v-if="scheduleError"
            role="alert"
            class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            {{ scheduleError }}
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <th class="py-2 pr-3">{{ $t('Desde') }}</th>
                        <th class="py-2 pr-3">{{ $t('Hasta') }}</th>
                        <th class="py-2 pr-3">{{ $t('Porcentaje (%)') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(tier, index) in rows"
                        :key="index"
                        class="border-t border-border"
                    >
                        <td class="py-2 pr-3 font-mono text-muted-foreground">
                            {{ formatCurrency(tier.from, currency) }}
                        </td>
                        <td class="py-2 pr-3">
                            <template v-if="index === rows.length - 1">
                                <span class="text-muted-foreground">{{
                                    $t('Sin límite')
                                }}</span>
                            </template>
                            <template v-else>
                                <Input
                                    :model-value="tier.to ?? ''"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-32"
                                    :aria-label="
                                        $t('Hasta del rango :position', {
                                            position: index + 1,
                                        })
                                    "
                                    :aria-invalid="Boolean(toErrorFor(index))"
                                    :disabled="disabled"
                                    @update:model-value="
                                        (v) => updateTo(index, String(v))
                                    "
                                />
                                <p
                                    v-if="toErrorFor(index)"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ toErrorFor(index) }}
                                </p>
                            </template>
                        </td>
                        <td class="py-2 pr-3">
                            <Input
                                :model-value="tier.rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="w-28"
                                :aria-label="
                                    $t('Porcentaje del rango :position', {
                                        position: index + 1,
                                    })
                                "
                                :aria-invalid="Boolean(rateErrorFor(index))"
                                :disabled="disabled"
                                @update:model-value="
                                    (v) => updateRate(index, String(v))
                                "
                            />
                            <p
                                v-if="rateErrorFor(index)"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ rateErrorFor(index) }}
                            </p>
                        </td>
                        <td class="py-2 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="disabled || rows.length <= 1"
                                :title="$t('Quitar rango')"
                                @click="removeTier(index)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">{{
                                    $t('Quitar rango')
                                }}</span>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="disabled || rows.length >= MAX_TIERS"
            @click="addTier"
        >
            <Plus class="mr-1 size-4" />
            {{ $t('Agregar rango') }}
        </Button>

        <div class="space-y-1">
            <Label for="commission-max-charge">{{
                $t('Tope máximo por reserva (opcional)')
            }}</Label>
            <Input
                id="commission-max-charge"
                :model-value="maxCharge ?? ''"
                type="number"
                min="0"
                step="0.01"
                class="w-48"
                :placeholder="$t('Sin tope')"
                :aria-invalid="Boolean(maxChargeError)"
                :disabled="disabled"
                @update:model-value="
                    (v) => (maxCharge = v === '' ? null : String(v))
                "
            />
            <p class="text-xs text-muted-foreground">
                {{
                    $t(
                        'Si el porcentaje supera este valor, Montree cobra solo el tope.',
                    )
                }}
            </p>
            <p v-if="maxChargeError" class="text-xs text-destructive">
                {{ maxChargeError }}
            </p>
        </div>

        <div class="rounded-md border border-border bg-muted/40 p-4">
            <h3 class="text-sm font-semibold text-foreground">
                {{ $t('Calculadora de ejemplo') }}
            </h3>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ $t('Si la reserva vale X, ¿cuánto cobra Montree?') }}
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div class="space-y-1">
                    <Label for="commission-example-amount">{{
                        $t('Valor de la reserva')
                    }}</Label>
                    <Input
                        id="commission-example-amount"
                        v-model="exampleAmount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-48"
                    />
                </div>

                <p v-if="exampleResult" class="text-sm text-foreground">
                    {{ $t('Montree cobra') }}
                    <span class="font-semibold">{{
                        formatCurrency(exampleResult.amount, currency)
                    }}</span>
                    <span
                        v-if="exampleResult.capped"
                        class="text-muted-foreground"
                    >
                        ({{ $t('tope aplicado') }})
                    </span>
                </p>
            </div>
        </div>
    </div>
</template>
