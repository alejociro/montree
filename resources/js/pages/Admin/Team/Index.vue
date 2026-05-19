<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    index as indexUsers,
    store as storeUser,
    updateRole,
    suspend,
    reactivate,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';

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

const confirmDialog = ref(false);
const confirmMember = ref<Member | null>(null);
const confirmNewRole = ref('');
const confirmMessage = ref('');
const changingRole = ref(false);

async function load() {
    loading.value = true;
    try {
        const res = await fetch(indexUsers().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        members.value = json.data;
    } finally {
        loading.value = false;
    }
}

function invite() {
    sending.value = true;
    router.post(
        storeUser().url,
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

function onRoleChange(member: Member, newRole: string) {
    const isSensitive =
        newRole === 'admin' ||
        newRole === 'customer' ||
        (member.role === 'admin' && newRole !== 'admin');

    if (isSensitive) {
        confirmMember.value = member;
        confirmNewRole.value = newRole;
        confirmMessage.value =
            newRole === 'admin'
                ? `¿Promover a ${member.name} a administrador? Tendrá acceso completo al panel.`
                : newRole === 'customer'
                  ? `¿Bajar a ${member.name} a cliente? Perderá acceso al panel de administración.`
                  : `¿Cambiar el rol de ${member.name} de ${member.role} a ${newRole}?`;
        confirmDialog.value = true;
        return;
    }

    executeRoleChange(member.id, newRole);
}

function confirmRoleChange() {
    if (!confirmMember.value) {
        return;
    }
    executeRoleChange(confirmMember.value.id, confirmNewRole.value);
    confirmDialog.value = false;
}

function cancelRoleChange() {
    confirmDialog.value = false;
    load();
}

function executeRoleChange(userId: number, role: string) {
    changingRole.value = true;
    router.patch(
        updateRole.url(userId),
        { role },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Rol actualizado');
                load();
            },
            onError: (e) => {
                toast.error(Object.values(e)[0] as string ?? 'Error');
                load();
            },
            onFinish: () => {
                changingRole.value = false;
            },
        },
    );
}

function doSuspend(memberId: number) {
    router.patch(suspend.url(memberId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Miembro suspendido');
            load();
        },
    });
}

function doReactivate(memberId: number) {
    router.patch(reactivate.url(memberId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Miembro reactivado');
            load();
        },
    });
}

function roleLabel(role: string | null): string {
    const labels: Record<string, string> = {
        admin: 'Admin',
        operator: 'Operador',
        guide: 'Guía',
        customer: 'Cliente',
    };
    return labels[role ?? ''] ?? role ?? 'Sin rol';
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
                        <option value="operator">Operador</option>
                        <option value="guide">Guía</option>
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
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="font-medium">{{ m.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ m.email }}</p>
                        </div>
                        <Badge
                            :variant="m.status === 'suspended' ? 'destructive' : 'secondary'"
                        >
                            {{ m.status === 'suspended' ? 'Suspendido' : 'Activo' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-3">
                        <select
                            :value="m.role"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                            :disabled="changingRole"
                            @change="onRoleChange(m, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="admin">Admin</option>
                            <option value="operator">Operador</option>
                            <option value="guide">Guía</option>
                            <option value="customer">Cliente</option>
                        </select>
                        <Button
                            v-if="m.status !== 'suspended'"
                            variant="outline"
                            size="sm"
                            @click="doSuspend(m.id)"
                        >
                            Suspender
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            @click="doReactivate(m.id)"
                        >
                            Reactivar
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <Dialog v-model:open="confirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar cambio de rol</DialogTitle>
                    <DialogDescription>{{ confirmMessage }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelRoleChange">Cancelar</Button>
                    <Button @click="confirmRoleChange">Confirmar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
