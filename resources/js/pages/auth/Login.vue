<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenant } from '@/composables/useTenant';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Ingresa tu correo y contraseña para acceder',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const { tenant, displayName } = useTenant();

const SUSPENSION_KEYWORDS = ['suspendida', 'suspended', 'suspendido'] as const;

function isSuspensionError(message: string | undefined): boolean {
    if (!message) {
        return false;
    }

    const normalized = message.toLowerCase();

    return SUSPENSION_KEYWORDS.some((keyword) => normalized.includes(keyword));
}

const contactEmail = computed(() => tenant.value?.contact_email ?? null);
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-primary"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <Alert
            v-if="isSuspensionError(errors.email)"
            variant="destructive"
            class="mb-2"
        >
            <AlertTriangle class="size-4" />
            <AlertTitle>Cuenta suspendida</AlertTitle>
            <AlertDescription>
                <p>{{ errors.email }}</p>
                <p v-if="contactEmail" class="mt-2">
                    Contacta al administrador:
                    <a
                        :href="`mailto:${contactEmail}`"
                        class="font-medium underline"
                    >
                        {{ contactEmail }}
                    </a>
                </p>
            </AlertDescription>
        </Alert>

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="tu@correo.com"
                />
                <InputError
                    v-if="!isSuspensionError(errors.email)"
                    :message="errors.email"
                />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Contraseña"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Recordarme</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Iniciar sesión
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            ¿No tienes una cuenta?
            <TextLink :href="register()" :tabindex="5">Regístrate</TextLink>
        </div>
    </Form>
</template>
