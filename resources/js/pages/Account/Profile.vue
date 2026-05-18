<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const page = usePage();
const user = page.props.auth?.user;

const form = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
});

function submit() {
    form.put('/api/v1/account/profile', {
        preserveScroll: true,
        onSuccess: () => toast.success('Perfil actualizado'),
        onError: () => toast.error('No pudimos actualizar el perfil'),
    });
}
</script>

<template>
    <Head title="Mi perfil" />
    <div class="container mx-auto max-w-2xl space-y-6 px-4 py-8">
        <h1 class="text-2xl font-bold">Mi perfil</h1>
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="name">Nombre</Label>
                <Input id="name" v-model="form.name" required />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>
            <div class="space-y-2">
                <Label for="phone">Teléfono</Label>
                <Input id="phone" v-model="form.phone" />
            </div>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
            </Button>
        </form>
    </div>
</template>
