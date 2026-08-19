<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenant } from '@/composables/useTenant';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crear cuenta',
        description: 'Ingresa tus datos para registrarte',
    },
});

const { isResolved, displayName } = useTenant();
</script>

<template>
    <Head :title="$t('Registrarse')" />

    <p v-if="isResolved" class="mb-4 text-center text-sm text-muted-foreground">
        {{ $t('Crea tu cuenta en') }}
        <span class="font-medium text-primary">{{ displayName }}</span>
        {{ $t('para reservar tours y guardar tus favoritos.') }}
    </p>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing, clearErrors }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{{ $t('Nombre completo') }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    :placeholder="$t('Tu nombre completo')"
                    @input="clearErrors('name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Correo electrónico') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    :placeholder="$t('tu@correo.com')"
                    @input="clearErrors('email')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('Contraseña') }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="$t('Contraseña')"
                    :passwordrules="passwordRules"
                    @input="clearErrors('password', 'password_confirmation')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('Confirmar contraseña')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="$t('Confirmar contraseña')"
                    :passwordrules="passwordRules"
                    @input="clearErrors('password_confirmation')"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ $t('Crear cuenta') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ $t('¿Ya tienes una cuenta?') }}
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
            >
                {{ $t('Inicia sesión') }}
            </TextLink>
        </div>
    </Form>
</template>
