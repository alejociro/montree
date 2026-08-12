<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeTenantUser } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantUserController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApi  } from '@/composables/useApi';
import type {ApiErrors} from '@/composables/useApi';

const props = defineProps<{
    tenantId: number;
}>();

const emit = defineEmits<{
    created: [];
}>();

const api = useApi();

const open = ref(false);
const processing = ref(false);
const errors = ref<ApiErrors>({});

const form = reactive({
    name: '',
    email: '',
    role: 'guide' as 'admin' | 'operator' | 'guide',
});

function reset(): void {
    form.name = '';
    form.email = '';
    form.role = 'guide';
    errors.value = {};
}

watch(open, (isOpen) => {
    if (!isOpen) {
        reset();
    }
});

function submit(): void {
    processing.value = true;
    errors.value = {};

    void api.post(
        storeTenantUser(props.tenantId).url,
        { name: form.name, email: form.email, role: form.role },
        {
            onSuccess: () => {
                toast.success('Usuario agregado. Se envió la invitación.');
                open.value = false;
                emit('created');
            },
            onError: (e) => {
                errors.value = e;
                toast.error(e._global ?? 'No se pudo agregar el usuario.');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Agregar usuario</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Agregar usuario</DialogTitle>
                <DialogDescription>
                    Agrega un miembro al equipo de esta agencia. Recibirá un correo
                    para establecer su contraseña.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="user-name">Nombre</Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        placeholder="Carlos Díaz"
                        autocomplete="off"
                    />
                    <p v-if="errors.name" class="text-xs text-red-600">
                        {{ errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        placeholder="carlos@agencia.com"
                        autocomplete="off"
                    />
                    <p v-if="errors.email" class="text-xs text-red-600">
                        {{ errors.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Rol</Label>
                    <Select v-model="form.role">
                        <SelectTrigger>
                            <SelectValue placeholder="Seleccionar rol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="operator">Operador</SelectItem>
                            <SelectItem value="guide">Guía</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.role" class="text-xs text-red-600">
                        {{ errors.role }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="open = false"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Agregando…' : 'Agregar usuario' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
