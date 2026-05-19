<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexPromotions,
    store as storePromotion,
    update as updatePromotion,
    destroy as destroyPromotion,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/PromotionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';

const api = useApi();

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

const stats = computed(() => {
    const total = items.value.length;
    const active = items.value.filter(
        (p) => p.is_active && !p.is_expired && !p.is_exhausted,
    ).length;
    const totalUses = items.value.reduce((sum, p) => sum + p.uses_count, 0);

    return { total, active, totalUses };
});

const showCreateForm = ref(false);
const editDialog = ref(false);
const editingPromotion = ref<Promotion | null>(null);

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

function submitCreate() {
    submitting.value = true;
    void api.post(
        storePromotion().url,
        { ...form.value },
        {
            onSuccess: () => {
                toast.success('Promoción creada');
                showCreateForm.value = false;
                form.value = defaultForm();
                void load();
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function openEdit(promotion: Promotion) {
    editingPromotion.value = promotion;
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
    editDialog.value = true;
}

function submitEdit() {
    if (!editingPromotion.value) {
        return;
    }

    submitting.value = true;
    void api.put(
        updatePromotion.url(editingPromotion.value.id),
        { ...form.value },
        {
            onSuccess: () => {
                toast.success('Promoción actualizada');
                editDialog.value = false;
                editingPromotion.value = null;
                void load();
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function deactivate(id: number) {
    void api.delete(destroyPromotion.url(id), {
        onSuccess: () => {
            toast.success('Promoción desactivada');
            void load();
        },
    });
}

function promotionState(p: Promotion): {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
} {
    if (p.is_expired) {
        return { label: 'Expirada', variant: 'outline' };
    }

    if (p.is_exhausted) {
        return { label: 'Agotada', variant: 'outline' };
    }

    if (!p.is_active) {
        return { label: 'Inactiva', variant: 'secondary' };
    }

    return { label: 'Activa', variant: 'default' };
}

function usagePercent(p: Promotion): number {
    if (!p.max_uses || p.max_uses === 0) {
        return 0;
    }

    return Math.min(100, Math.round((p.uses_count / p.max_uses) * 100));
}

onMounted(load);
</script>

<template>
    <Head title="Promociones" />
    <div class="container mx-auto max-w-4xl space-y-6 px-4 py-8">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Promociones</h1>
            <Button @click="showCreateForm = !showCreateForm">
                {{ showCreateForm ? 'Cancelar' : 'Nueva promoción' }}
            </Button>
        </header>

        <div v-if="!loading" class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border p-4 text-center">
                <p class="text-2xl font-bold">{{ stats.total }}</p>
                <p class="text-sm text-muted-foreground">Total</p>
            </div>
            <div class="rounded-lg border p-4 text-center">
                <p class="text-2xl font-bold text-primary">
                    {{ stats.active }}
                </p>
                <p class="text-sm text-muted-foreground">Activas</p>
            </div>
            <div class="rounded-lg border p-4 text-center">
                <p class="text-2xl font-bold">{{ stats.totalUses }}</p>
                <p class="text-sm text-muted-foreground">Usos totales</p>
            </div>
        </div>

        <section v-if="showCreateForm" class="space-y-4 rounded-lg border p-6">
            <h2 class="text-lg font-semibold">Nueva promoción</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="code">Código</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        placeholder="VERANO2026"
                        maxlength="40"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="type">Tipo</Label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="percentage">Porcentaje</option>
                        <option value="fixed">Monto fijo</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="value">Valor</Label>
                    <Input
                        id="value"
                        v-model="form.value"
                        type="number"
                        min="1"
                        step="0.01"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="max">Máx. usos (opcional)</Label>
                    <Input
                        id="max"
                        v-model.number="form.max_uses"
                        type="number"
                        min="1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="start">Desde</Label>
                    <Input id="start" v-model="form.starts_at" type="date" />
                </div>
                <div class="space-y-2">
                    <Label for="end">Hasta</Label>
                    <Input id="end" v-model="form.ends_at" type="date" />
                </div>
            </div>
            <Button :disabled="submitting || !form.code" @click="submitCreate">
                {{ submitting ? 'Guardando...' : 'Crear' }}
            </Button>
        </section>

        <section class="space-y-3">
            <p v-if="loading" class="text-sm text-muted-foreground">
                Cargando...
            </p>
            <p v-else-if="items.length === 0" class="text-muted-foreground">
                No hay promociones todavía.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="p in items"
                    :key="p.id"
                    class="cursor-pointer rounded-lg border p-4 transition-colors hover:bg-muted/50"
                    @click="openEdit(p)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="font-mono font-bold">{{ p.code }}</p>
                                <Badge :variant="promotionState(p).variant">
                                    {{ promotionState(p).label }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    p.type === 'percentage'
                                        ? `${p.value}%`
                                        : `$${p.value}`
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                v-if="p.max_uses"
                                class="w-32 space-y-1 text-right"
                            >
                                <p class="text-xs text-muted-foreground">
                                    {{ p.uses_count }}/{{ p.max_uses }} usos
                                </p>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{
                                            width: `${usagePercent(p)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ p.uses_count }} usos
                            </p>
                            <Button
                                v-if="p.is_active"
                                variant="ghost"
                                size="sm"
                                @click.stop="deactivate(p.id)"
                            >
                                Desactivar
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <Dialog v-model:open="editDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar promoción</DialogTitle>
                </DialogHeader>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="edit-code">Código</Label>
                        <Input
                            id="edit-code"
                            v-model="form.code"
                            maxlength="40"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-type">Tipo</Label>
                        <select
                            id="edit-type"
                            v-model="form.type"
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="percentage">Porcentaje</option>
                            <option value="fixed">Monto fijo</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-value">Valor</Label>
                        <Input
                            id="edit-value"
                            v-model="form.value"
                            type="number"
                            min="1"
                            step="0.01"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-max">Máx. usos (opcional)</Label>
                        <Input
                            id="edit-max"
                            v-model.number="form.max_uses"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-start">Desde</Label>
                        <Input
                            id="edit-start"
                            v-model="form.starts_at"
                            type="date"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-end">Hasta</Label>
                        <Input
                            id="edit-end"
                            v-model="form.ends_at"
                            type="date"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editDialog = false"
                        >Cancelar</Button
                    >
                    <Button
                        :disabled="submitting || !form.code"
                        @click="submitEdit"
                    >
                        {{ submitting ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
