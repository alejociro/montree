<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeTenant } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantController';
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
import type { TenantPlan } from '@/types';

const { t } = useTranslations();

const emit = defineEmits<{
    created: [tenantId: number];
}>();

const api = useApi();

const open = ref(false);
const processing = ref(false);
const slugTouched = ref(false);
const errors = ref<ApiErrors>({});

const form = reactive({
    name: '',
    slug: '',
    plan: 'basic' as TenantPlan,
    admin_name: '',
    admin_email: '',
});

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);
}

watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

function reset(): void {
    form.name = '';
    form.slug = '';
    form.plan = 'basic';
    form.admin_name = '';
    form.admin_email = '';
    slugTouched.value = false;
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

    void api.post<{ data: { id: number } }>(
        storeTenant().url,
        {
            name: form.name,
            slug: form.slug,
            plan: form.plan,
            admin_name: form.admin_name,
            admin_email: form.admin_email,
        },
        {
            onSuccess: (response) => {
                toast.success(
                    t('Tenant creado. Se envió la invitación al admin.'),
                );
                open.value = false;

                if (response?.data?.id) {
                    emit('created', response.data.id);
                }
            },
            onError: (e) => {
                errors.value = e;
                toast.error(e._global ?? t('No se pudo crear el tenant.'));
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
            <Button>{{ $t('Nuevo tenant') }}</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('Crear tenant') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Registra una nueva agencia y su administrador inicial. El admin recibirá un correo para establecer su contraseña.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="tenant-name">{{
                        $t('Nombre de la agencia')
                    }}</Label>
                    <Input
                        id="tenant-name"
                        v-model="form.name"
                        :placeholder="$t('Eco Adventures')"
                        autocomplete="off"
                    />
                    <p v-if="errors.name" class="text-xs text-destructive">
                        {{ errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="tenant-slug">{{
                        $t('Slug (subdominio)')
                    }}</Label>
                    <Input
                        id="tenant-slug"
                        v-model="form.slug"
                        :placeholder="$t('eco-adventures')"
                        class="font-mono"
                        autocomplete="off"
                        @input="slugTouched = true"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('La agencia vivirá en') }}
                        <span class="font-mono">{{ form.slug || 'slug' }}</span
                        >.montree.app
                    </p>
                    <p v-if="errors.slug" class="text-xs text-destructive">
                        {{ errors.slug }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>{{ $t('Plan') }}</Label>
                    <Select v-model="form.plan">
                        <SelectTrigger>
                            <SelectValue
                                :placeholder="$t('Seleccionar plan')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="basic">{{
                                $t('Basic')
                            }}</SelectItem>
                            <SelectItem value="professional">
                                {{ $t('Professional') }}
                            </SelectItem>
                            <SelectItem value="enterprise">
                                {{ $t('Enterprise') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.plan" class="text-xs text-destructive">
                        {{ errors.plan }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="admin-name">{{
                            $t('Nombre del admin')
                        }}</Label>
                        <Input
                            id="admin-name"
                            v-model="form.admin_name"
                            :placeholder="$t('Jane Pérez')"
                            autocomplete="off"
                        />
                        <p
                            v-if="errors.admin_name"
                            class="text-xs text-destructive"
                        >
                            {{ errors.admin_name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="admin-email">{{
                            $t('Email del admin')
                        }}</Label>
                        <Input
                            id="admin-email"
                            v-model="form.admin_email"
                            type="email"
                            :placeholder="$t('jane@agencia.com')"
                            autocomplete="off"
                        />
                        <p
                            v-if="errors.admin_email"
                            class="text-xs text-destructive"
                        >
                            {{ errors.admin_email }}
                        </p>
                    </div>
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
                        {{ processing ? $t('Creando…') : $t('Crear tenant') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
