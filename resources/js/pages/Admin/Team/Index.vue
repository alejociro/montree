<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    status: string;
    joined_at: string | null;
};

const members = ref<Member[]>([]);
const loading = ref(true);
const inviteEmail = ref('');
const inviteName = ref('');
const inviteRole = ref<'admin' | 'operator' | 'guide'>('guide');
const sending = ref(false);

async function load() {
    loading.value = true;
    const res = await fetch('/api/v1/admin/users', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const json = await res.json();
    members.value = json.data;
    loading.value = false;
}

function invite() {
    sending.value = true;
    router.post(
        '/api/v1/admin/users',
        { email: inviteEmail.value, name: inviteName.value || null, role: inviteRole.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Invitación enviada');
                inviteEmail.value = '';
                inviteName.value = '';
                load();
            },
            onError: (e) => toast.error(Object.values(e)[0] as string),
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function suspend(memberId: number) {
    router.patch(`/api/v1/admin/users/${memberId}/suspend`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Miembro suspendido');
            load();
        },
    });
}

function reactivate(memberId: number) {
    router.patch(`/api/v1/admin/users/${memberId}/reactivate`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Miembro reactivado');
            load();
        },
    });
}

onMounted(load);
</script>

<template>
    <Head title="Equipo" />
    <div class="container mx-auto max-w-4xl space-y-8 px-4 py-8">
        <h1 class="text-2xl font-bold">Equipo</h1>

        <section class="space-y-4 rounded-lg border p-6">
            <h2 class="text-lg font-semibold">Invitar miembro</h2>
            <div class="grid gap-3 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="ie">Email</Label>
                    <Input id="ie" v-model="inviteEmail" type="email" />
                </div>
                <div class="space-y-2">
                    <Label for="in">Nombre</Label>
                    <Input id="in" v-model="inviteName" />
                </div>
                <div class="space-y-2">
                    <Label for="ir">Rol</Label>
                    <select
                        id="ir"
                        v-model="inviteRole"
                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="admin">Admin</option>
                        <option value="operator">Operator</option>
                        <option value="guide">Guide</option>
                    </select>
                </div>
            </div>
            <Button :disabled="sending || !inviteEmail" @click="invite">
                {{ sending ? 'Enviando...' : 'Invitar' }}
            </Button>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Miembros</h2>
            <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>
            <ul v-else class="space-y-2">
                <li
                    v-for="m in members"
                    :key="m.id"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div>
                        <p class="font-medium">{{ m.name }} <span class="text-xs text-muted-foreground">({{ m.role }})</span></p>
                        <p class="text-sm text-muted-foreground">{{ m.email }} · {{ m.status }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="m.status !== 'suspended'" variant="outline" size="sm" @click="suspend(m.id)">Suspender</Button>
                        <Button v-else variant="outline" size="sm" @click="reactivate(m.id)">Reactivar</Button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
