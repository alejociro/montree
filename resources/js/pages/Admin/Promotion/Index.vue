<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Promotion = {
    id: number;
    code: string;
    type: 'percentage' | 'fixed';
    value: string;
    max_uses: number | null;
    uses_count: number;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    is_expired: boolean;
    is_exhausted: boolean;
};

const items = ref<Promotion[]>([]);
const loading = ref(true);

const showForm = ref(false);
const form = ref({
    code: '',
    type: 'percentage' as 'percentage' | 'fixed',
    value: '10.00',
    starts_at: new Date().toISOString().slice(0, 10),
    ends_at: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    max_uses: null as number | null,
    max_uses_per_user: 1,
    is_active: true,
});

const submitting = ref(false);

async function load() {
    loading.value = true;
    const res = await fetch('/api/v1/admin/promotions', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const json = await res.json();
    items.value = json.data ?? [];
    loading.value = false;
}

function submit() {
    submitting.value = true;
    router.post('/api/v1/admin/promotions', { ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Promoción creada');
            showForm.value = false;
            form.value.code = '';
            load();
        },
        onError: (e) => toast.error(Object.values(e)[0] as string),
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function deactivate(id: number) {
    router.delete(`/api/v1/admin/promotions/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Promoción desactivada');
            load();
        },
    });
}

onMounted(load);
</script>

<template>
    <Head title="Promociones" />
    <div class="container mx-auto max-w-4xl space-y-6 px-4 py-8">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Promociones</h1>
            <Button @click="showForm = !showForm">
                {{ showForm ? 'Cancelar' : 'Nueva promoción' }}
            </Button>
        </header>

        <section v-if="showForm" class="space-y-4 rounded-lg border p-6">
            <h2 class="text-lg font-semibold">Nueva promoción</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="code">Código</Label>
                    <Input id="code" v-model="form.code" placeholder="VERANO2026" maxlength="40" />
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
                    <Input id="value" v-model="form.value" type="number" min="1" step="0.01" />
                </div>
                <div class="space-y-2">
                    <Label for="max">Máx. usos (opcional)</Label>
                    <Input id="max" v-model.number="form.max_uses" type="number" min="1" />
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
            <Button :disabled="submitting || !form.code" @click="submit">
                {{ submitting ? 'Guardando...' : 'Crear' }}
            </Button>
        </section>

        <section class="space-y-3">
            <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>
            <p v-else-if="items.length === 0" class="text-muted-foreground">
                No hay promociones todavía.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="p in items"
                    :key="p.id"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div>
                        <p class="font-mono font-bold">{{ p.code }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ p.type === 'percentage' ? `${p.value}%` : `$${p.value}` }} ·
                            {{ p.uses_count }}{{ p.max_uses ? '/' + p.max_uses : '' }} usos
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="p.is_expired" variant="outline">Expirada</Badge>
                        <Badge v-else-if="p.is_exhausted" variant="outline">Agotada</Badge>
                        <Badge v-else-if="!p.is_active" variant="outline">Inactiva</Badge>
                        <Badge v-else>Activa</Badge>
                        <Button v-if="p.is_active" variant="ghost" size="sm" @click="deactivate(p.id)">
                            Desactivar
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
