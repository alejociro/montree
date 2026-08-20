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
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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
    role: 'guide' as 'admin' | 'sales' | 'operator' | 'guide',
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
                toast.success(t('Usuario agregado. Se envió la invitación.'));
                open.value = false;
                emit('created');
            },
            onError: (e) => {
                errors.value = e;
                toast.error(e._global ?? t('No se pudo agregar el usuario.'));
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
                    <p v-if="errors.name" class="text-xs text-destructive">
                        {{ errors.name }}
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
                    <p v-if="errors.email" class="text-xs text-destructive">
                        {{ errors.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>{{ $t('Rol') }}</Label>
                    <Select v-model="form.role">
                        <SelectTrigger>
                            <SelectValue :placeholder="$t('Seleccionar rol')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="admin">{{
                                $t('Admin')
                            }}</SelectItem>
                            <SelectItem value="sales">{{
                                $t('Vendedor')
                            }}</SelectItem>
                            <SelectItem value="operator">{{
                                $t('Operador')
                            }}</SelectItem>
                            <SelectItem value="guide">{{
                                $t('Guía')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.role" class="text-xs text-destructive">
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
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{
                            processing
                                ? $t('Agregando…')
                                : $t('Agregar usuario')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
