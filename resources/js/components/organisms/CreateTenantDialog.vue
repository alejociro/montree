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
import { store as storeTenant } from '@/routes/super-admin/tenants';
import { CURRENCY_VALUES } from '@/types/enums.generated';
import type { Currency } from '@/types/enums.generated';

const { t } = useTranslations();

const open = ref(false);
const slugTouched = ref(false);

const form = useForm({
    name: '',
    slug: '',
    currency: 'COP' as Currency,
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

watch(open, (isOpen) => {
    if (!isOpen) {
        form.reset();
        form.clearErrors();
        slugTouched.value = false;
    }
});

function submit(): void {
    form.post(storeTenant.url(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: () => {
            toast.error(t('No se pudo crear la agencia.'));
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>{{ $t('Nueva agencia') }}</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('Crear agencia') }}</DialogTitle>
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
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
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
                    <p v-if="form.errors.slug" class="text-xs text-destructive">
                        {{ form.errors.slug }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>{{ $t('Moneda') }}</Label>
                    <Select v-model="form.currency">
                        <SelectTrigger>
                            <SelectValue
                                :placeholder="$t('Seleccionar moneda')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="code in CURRENCY_VALUES"
                                :key="code"
                                :value="code"
                            >
                                {{ code }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Toda la agencia opera en esta moneda. Se puede cambiar después en su configuración.',
                            )
                        }}
                    </p>
                    <p
                        v-if="form.errors.currency"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.currency }}
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
                            v-if="form.errors.admin_name"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.admin_name }}
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
                            v-if="form.errors.admin_email"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.admin_email }}
                        </p>
                    </div>
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
                                ? $t('Creando…')
                                : $t('Crear agencia')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
