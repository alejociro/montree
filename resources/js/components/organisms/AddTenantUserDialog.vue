<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { useTranslations } from '@/composables/useTranslations';
import { store as storeTenantUser } from '@/routes/super-admin/tenants/users';

const { t } = useTranslations();

const props = defineProps<{
    tenantId: number;
    roles: string[];
}>();

const ROLE_LABELS: Record<string, string> = {
    admin: t('Admin'),
    sales: t('Vendedor'),
    operator: t('Operador'),
    guide: t('Guía'),
};

const open = ref(false);

const form = useForm({
    name: '',
    email: '',
    role: props.roles[0] ?? 'guide',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role;
}

function submit(): void {
    form.post(storeTenantUser.url(props.tenantId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: () => {
            toast.error(t('No se pudo agregar el usuario.'));
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">{{
                $t('Agregar usuario')
            }}</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('Agregar usuario') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Agrega un miembro al equipo de esta agencia. Recibirá un correo para establecer su contraseña.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="user-name">{{ $t('Nombre') }}</Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        :placeholder="$t('Carlos Díaz')"
                        autocomplete="off"
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="user-email">{{ $t('Email') }}</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        :placeholder="$t('carlos@agencia.com')"
                        autocomplete="off"
                    />
                    <p v-if="form.errors.email" class="text-xs text-destructive">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>{{ $t('Rol') }}</Label>
                    <Select v-model="form.role">
                        <SelectTrigger>
                            <SelectValue :placeholder="$t('Seleccionar rol')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in roles"
                                :key="role"
                                :value="role"
                            >
                                {{ roleLabel(role) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.role" class="text-xs text-destructive">
                        {{ form.errors.role }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                    >
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? $t('Agregando…')
                                : $t('Agregar usuario')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
