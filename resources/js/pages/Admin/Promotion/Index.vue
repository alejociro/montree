<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    Copy,
    Loader2,
    Pencil,
    Plus,
    TicketPercent,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyPromotion,
    index as indexPromotions,
    store as storePromotion,
    update as updatePromotion,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/PromotionController';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatCurrency,
    formatDate,
    formatDayDistance,
    formatNumber,
} from '@/lib/format';

const { t } = useTranslations();

const api = useApi();
const { currency } = useTenant();

type Promotion = {
    id: number;
    code: string;
    name: string | null;
    description: string | null;
    type: 'percentage' | 'fixed';
    value: string;
    max_discount: string | null;
    min_amount: string | null;
    max_uses: number | null;
    uses_count: number;
    max_uses_per_user: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    is_expired: boolean;
    is_exhausted: boolean;
    applicable_tours: number[];
    created_at: string | null;
};

const items = ref<Promotion[]>([]);
const loading = ref(true);

function isLive(promotion: Promotion): boolean {
    return (
        promotion.is_active && !promotion.is_expired && !promotion.is_exhausted
    );
}

/** Vence dentro del mes en curso: el KPI que anticipa trabajo. */
function expiresThisMonth(promotion: Promotion): boolean {
    if (promotion.ends_at === null || promotion.is_expired) {
        return false;
    }

    const end = new Date(promotion.ends_at);
    const now = new Date();

    return (
        end.getFullYear() === now.getFullYear() &&
        end.getMonth() === now.getMonth()
    );
}

const stats = computed(() => ({
    total: items.value.length,
    live: items.value.filter(isLive).length,
    uses: items.value.reduce((sum, item) => sum + item.uses_count, 0),
    expiring: items.value.filter(expiresThisMonth).length,
}));

const dialogOpen = ref(false);
const editing = ref<Promotion | null>(null);
const errors = ref<Record<string, string | undefined>>({});

const defaultForm = () => ({
    code: '',
    type: 'percentage' as 'percentage' | 'fixed',
    value: '10.00',
    starts_at: new Date().toISOString().slice(0, 10),
    ends_at: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    max_uses: null as number | null,
    max_uses_per_user: 1,
    is_active: true,
});

const form = ref(defaultForm());
const submitting = ref(false);
const togglingId = ref<number | null>(null);

async function load() {
    loading.value = true;

    try {
        const res = await fetch(indexPromotions().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        items.value = json.data ?? [];
    } finally {
        loading.value = false;
    }
}

function openCreate(): void {
    editing.value = null;
    errors.value = {};
    form.value = defaultForm();
    dialogOpen.value = true;
}

function openEdit(promotion: Promotion): void {
    editing.value = promotion;
    errors.value = {};
    form.value = {
        code: promotion.code,
        type: promotion.type,
        value: promotion.value,
        starts_at: promotion.starts_at ? promotion.starts_at.slice(0, 10) : '',
        ends_at: promotion.ends_at ? promotion.ends_at.slice(0, 10) : '',
        max_uses: promotion.max_uses,
        max_uses_per_user: promotion.max_uses_per_user ?? 1,
        is_active: promotion.is_active,
    };
    dialogOpen.value = true;
}

function clearError(field: string): void {
    if (errors.value[field] !== undefined) {
        errors.value = { ...errors.value, [field]: undefined };
    }
}

function submit(): void {
    submitting.value = true;
    errors.value = {};

    const target = editing.value;

    const handlers = {
        onSuccess: () => {
            toast.success(
                target === null
                    ? t('Promoción creada')
                    : t('Promoción actualizada'),
            );
            dialogOpen.value = false;
            editing.value = null;
            form.value = defaultForm();
            void load();
        },
        onError: (received: Record<string, string>) => {
            errors.value = received;

            if (received._global !== undefined) {
                toast.error(received._global);
            }
        },
        onFinish: () => {
            submitting.value = false;
        },
    };

    if (target === null) {
        void api.post(storePromotion().url, { ...form.value }, handlers);

        return;
    }

    void api.put(updatePromotion.url(target.id), { ...form.value }, handlers);
}

/**
 * Inhabilitar/Habilitar es el mismo interruptor: `DELETE` desactiva —el
 * backend no borra el código, lo apaga— y `PUT is_active` lo vuelve a
 * encender. Un código usado nunca se destruye: su histórico de usos importa.
 */
function toggleActive(promotion: Promotion): void {
    if (togglingId.value !== null) {
        return;
    }

    togglingId.value = promotion.id;

    const onFinish = () => {
        togglingId.value = null;
    };

    if (promotion.is_active) {
        void api.delete(destroyPromotion.url(promotion.id), {
            onSuccess: () => {
                toast.success(t('Promoción inhabilitada'));
                void load();
            },
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ??
                        t('No se pudo inhabilitar la promoción.'),
                ),
            onFinish,
        });

        return;
    }

    void api.put(
        updatePromotion.url(promotion.id),
        { is_active: true },
        {
            onSuccess: () => {
                toast.success(t('Promoción habilitada'));
                void load();
            },
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ??
                        t('No se pudo habilitar la promoción.'),
                ),
            onFinish,
        },
    );
}

async function copyCode(promotion: Promotion): Promise<void> {
    try {
        await navigator.clipboard.writeText(promotion.code);
        toast.success(t('Código copiado'));
    } catch {
        toast.error(t('No se pudo copiar el código.'));
    }
}

function discountLabel(promotion: Promotion): string {
    return promotion.type === 'percentage'
        ? `${Number(promotion.value)}%`
        : formatCurrency(promotion.value, currency.value ?? 'USD');
}

function typeLabel(promotion: Promotion): string {
    return promotion.type === 'percentage' ? t('Porcentaje') : t('Monto fijo');
}

function validityLabel(promotion: Promotion): string {
    const from =
        promotion.starts_at === null
            ? t('sin inicio')
            : formatDate(promotion.starts_at);
    const to =
        promotion.ends_at === null
            ? t('sin vencimiento')
            : formatDate(promotion.ends_at);

    return `${from} → ${to}`;
}

function validityHint(promotion: Promotion): string {
    if (promotion.is_expired) {
        return t('Vencida');
    }

    if (promotion.ends_at === null) {
        return t('No vence');
    }

    return t('Vence :when', { when: formatDayDistance(promotion.ends_at) });
}

function usageLabel(promotion: Promotion): string {
    return promotion.max_uses === null
        ? t(':count usos · sin límite', {
              count: formatNumber(promotion.uses_count),
          })
        : `${promotion.uses_count}/${promotion.max_uses}`;
}

function usagePercent(promotion: Promotion): number {
    if (promotion.max_uses === null || promotion.max_uses === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((promotion.uses_count / promotion.max_uses) * 100),
    );
}

function stateFor(promotion: Promotion): { label: string; classes: string } {
    if (promotion.is_expired) {
        return {
            label: t('Vencida'),
            classes: 'bg-brand-drop-50 text-brand-drop',
        };
    }

    if (promotion.is_exhausted) {
        return {
            label: t('Agotada'),
            classes: 'bg-brand-warn-50 text-brand-warn',
        };
    }

    if (!promotion.is_active) {
        return {
            label: t('Inhabilitada'),
            classes: 'bg-muted text-muted-foreground',
        };
    }

    return {
        label: t('Activa'),
        classes: 'bg-primary-soft text-primary-readable',
    };
}

/**
 * `max_uses` es opcional: el input entrega '' al vaciarse y el backend espera
 * `null`, no 0. `Input` no admite `null` como model-value, de ahí el puente.
 */
function setMaxUses(value: string | number): void {
    form.value.max_uses = value === '' ? null : Number(value);
    clearError('max_uses');
}

onMounted(load);
</script>

<template>
    <Head :title="$t('Promociones')" />

    <div class="px-4 py-6 md:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Promociones')"
                :description="
                    $t(
                        'Códigos de descuento, su vigencia y cuánto se han usado.',
                    )
                "
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                {{ $t('Nueva promoción') }}
            </Button>
        </div>

        <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                :label="$t('Códigos creados')"
                :value="formatNumber(stats.total)"
                :detail="$t('en total')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Vigentes')"
                :value="formatNumber(stats.live)"
                :detail="$t('aplicables hoy')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Usos totales')"
                :value="formatNumber(stats.uses)"
                :detail="$t('reservas con descuento')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Vencen este mes')"
                :value="formatNumber(stats.expiring)"
                :detail="$t('revisa si los renuevas')"
                :alert="stats.expiring > 0"
                :loading="loading"
            />
        </div>

        <div class="mt-5 rounded-2xl border border-border bg-card">
            <div v-if="loading" class="space-y-2 p-4">
                <div
                    v-for="n in 4"
                    :key="n"
                    class="h-14 animate-pulse rounded-lg bg-muted"
                />
            </div>

            <div
                v-else-if="items.length === 0"
                class="m-4 flex flex-col items-center gap-3 rounded-xl border border-dashed border-input p-12 text-center"
            >
                <TicketPercent class="size-8 text-muted-foreground/40" />
                <div class="space-y-1">
                    <p class="text-base font-medium">
                        {{ $t('Todavía no hay promociones') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Un código de descuento se aplica en el checkout y deja de servir solo cuando vence.',
                            )
                        }}
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('Nueva promoción') }}
                </Button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left">
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Código')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Descuento')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Vigencia')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Uso')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Estado')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3 text-right">{{
                                $t('Acciones')
                            }}</MonoLabel>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="promotion in items"
                            :key="promotion.id"
                            class="border-b border-brand-line-2 align-middle transition last:border-0 hover:bg-primary-soft/50"
                            :class="promotion.is_active ? '' : 'opacity-[.62]'"
                        >
                            <td class="px-4 py-3.5">
                                <span
                                    class="block font-mono text-[13.5px] font-bold tracking-wide"
                                >
                                    {{ promotion.code }}
                                </span>
                                <MonoLabel class="mt-1">{{
                                    typeLabel(promotion)
                                }}</MonoLabel>
                            </td>

                            <td
                                class="px-4 py-3.5 text-[15px] font-bold whitespace-nowrap tabular-nums"
                            >
                                {{ discountLabel(promotion) }}
                            </td>

                            <td class="px-4 py-3.5">
                                <span
                                    class="block text-[13px] whitespace-nowrap text-foreground"
                                >
                                    {{ validityLabel(promotion) }}
                                </span>
                                <span
                                    class="block text-xs"
                                    :class="
                                        promotion.is_expired
                                            ? 'text-brand-drop'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ validityHint(promotion) }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5">
                                <span
                                    class="block text-[13px] font-semibold tabular-nums"
                                >
                                    {{ usageLabel(promotion) }}
                                </span>
                                <div
                                    v-if="promotion.max_uses !== null"
                                    class="mt-1.5 h-1.5 w-[110px] overflow-hidden rounded-full bg-brand-line-2"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{
                                            width: `${usagePercent(promotion)}%`,
                                        }"
                                    />
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                                    :class="stateFor(promotion).classes"
                                >
                                    {{ stateFor(promotion).label }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="flex justify-end">
                                    <ActionMenu
                                        variant="ghost"
                                        :label="
                                            $t('Acciones de :name', {
                                                name: promotion.code,
                                            })
                                        "
                                    >
                                        <DropdownMenuItem
                                            @select="openEdit(promotion)"
                                        >
                                            <Pencil class="size-4" />
                                            {{ $t('Editar promoción') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @select="copyCode(promotion)"
                                        >
                                            <Copy class="size-4" />
                                            {{ $t('Copiar código') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="promotion.is_active"
                                            variant="destructive"
                                            :disabled="
                                                togglingId === promotion.id
                                            "
                                            @select="toggleActive(promotion)"
                                        >
                                            <Ban class="size-4" />
                                            {{ $t('Inhabilitar') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-else
                                            :disabled="
                                                togglingId === promotion.id
                                            "
                                            @select="toggleActive(promotion)"
                                        >
                                            <CheckCircle2 class="size-4" />
                                            {{ $t('Habilitar') }}
                                        </DropdownMenuItem>
                                    </ActionMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editing === null
                                ? $t('Nueva promoción')
                                : $t('Editar promoción')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Los códigos vencidos dejan de aplicarse solos: no hay que inhabilitarlos a mano.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3.5 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="promo-code">{{ $t('Código') }}</Label>
                        <Input
                            id="promo-code"
                            v-model="form.code"
                            :placeholder="$t('VERANO2026')"
                            maxlength="40"
                            class="font-mono uppercase"
                            :aria-invalid="
                                errors.code !== undefined || undefined
                            "
                            @update:model-value="clearError('code')"
                        />
                        <InputError :message="errors.code" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="promo-type">{{ $t('Tipo') }}</Label>
                        <select
                            id="promo-type"
                            v-model="form.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="percentage">
                                {{ $t('Porcentaje') }}
                            </option>
                            <option value="fixed">
                                {{ $t('Monto fijo') }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="promo-value">{{ $t('Valor') }}</Label>
                        <Input
                            id="promo-value"
                            v-model="form.value"
                            type="number"
                            min="1"
                            step="0.01"
                            :aria-invalid="
                                errors.value !== undefined || undefined
                            "
                            @update:model-value="clearError('value')"
                        />
                        <InputError :message="errors.value" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="promo-max">{{
                            $t('Máximo de usos (opcional)')
                        }}</Label>
                        <Input
                            id="promo-max"
                            type="number"
                            min="1"
                            :model-value="form.max_uses ?? ''"
                            :aria-invalid="
                                errors.max_uses !== undefined || undefined
                            "
                            @update:model-value="setMaxUses"
                        />
                        <InputError :message="errors.max_uses" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="promo-start">{{ $t('Desde') }}</Label>
                        <Input
                            id="promo-start"
                            v-model="form.starts_at"
                            type="date"
                            :aria-invalid="
                                errors.starts_at !== undefined || undefined
                            "
                            @update:model-value="clearError('starts_at')"
                        />
                        <InputError :message="errors.starts_at" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="promo-end">{{ $t('Hasta') }}</Label>
                        <Input
                            id="promo-end"
                            v-model="form.ends_at"
                            type="date"
                            :aria-invalid="
                                errors.ends_at !== undefined || undefined
                            "
                            @update:model-value="clearError('ends_at')"
                        />
                        <InputError :message="errors.ends_at" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dialogOpen = false">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button
                        :disabled="submitting || form.code.trim() === ''"
                        @click="submit"
                    >
                        <Loader2
                            v-if="submitting"
                            class="size-4 animate-spin"
                        />
                        {{
                            editing === null
                                ? $t('Crear promoción')
                                : $t('Guardar cambios')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
