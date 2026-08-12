<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { reactive, ref, toRef } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Api/V1/Onboarding/AgencyRegistrationController';
import InputError from '@/components/InputError.vue';
import SubdomainField from '@/components/molecules/SubdomainField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { useSubdomainAvailability } from '@/composables/useSubdomainAvailability';
import { checkEmail } from '@/routes/onboarding';
import type { AgencyRegistrationResponse } from '@/types/onboarding.types';

const form = reactive({
    agency_name: '',
    subdomain: '',
    founder_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const errors = reactive<Record<string, string>>({});
const globalError = ref<string | null>(null);
const processing = ref(false);

const api = useApi();

const { status: subdomainStatus, reason: subdomainReason } =
    useSubdomainAvailability(toRef(form, 'subdomain'));

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// WHY: server/client errors used to survive until the next submit, so fixing a
// short password left the "mínimo 8 caracteres" message on screen.
function clearFieldError(field: string) {
    delete errors[field];
    globalError.value = null;
}

function resetErrors() {
    for (const key of Object.keys(errors)) {
        delete errors[key];
    }

    globalError.value = null;
}

function validate(): boolean {
    resetErrors();

    if (form.agency_name.trim() === '') {
        errors.agency_name = 'Ingresa el nombre de tu agencia.';
    }

    if (form.subdomain.trim() === '') {
        errors.subdomain = 'Elige un subdominio.';
    } else if (subdomainStatus.value === 'unavailable') {
        errors.subdomain = 'Elige un subdominio disponible.';
    }

    if (form.founder_name.trim() === '') {
        errors.founder_name = 'Ingresa tu nombre.';
    }

    if (form.email.trim() === '') {
        errors.email = 'Ingresa tu correo electrónico.';
    } else if (!EMAIL_PATTERN.test(form.email)) {
        errors.email = 'Ingresa un correo electrónico válido.';
    }

    if (form.password === '') {
        errors.password = 'Ingresa una contraseña.';
    } else if (form.password.length < 8) {
        errors.password = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if (form.password_confirmation !== form.password) {
        errors.password_confirmation = 'Las contraseñas no coinciden.';
    }

    return Object.keys(errors).length === 0;
}

function applyServerErrors(serverErrors: ApiErrors) {
    for (const [field, message] of Object.entries(serverErrors)) {
        if (field === '_global') {
            globalError.value = message;

            continue;
        }

        errors[field] = message;
    }
}

function submit() {
    if (processing.value) {
        return;
    }

    if (!validate()) {
        return;
    }

    processing.value = true;

    api.post<AgencyRegistrationResponse>(
        store.url(),
        { ...form },
        {
            onSuccess: (data) => {
                router.visit(
                    checkEmail.url({
                        query: {
                            email: data.data.email,
                            agency: form.agency_name,
                        },
                    }),
                );
            },
            onError: (serverErrors) => {
                applyServerErrors(serverErrors);
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <div
            v-if="globalError"
            role="alert"
            class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
        >
            <AlertCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ globalError }}</span>
        </div>

        <div class="grid gap-2">
            <Label for="agency_name">Nombre de la agencia</Label>
            <Input
                id="agency_name"
                v-model="form.agency_name"
                type="text"
                autocomplete="organization"
                :tabindex="1"
                placeholder="Eco Adventures"
                :aria-invalid="Boolean(errors.agency_name)"
                @input="clearFieldError('agency_name')"
            />
            <InputError :message="errors.agency_name" />
        </div>

        <SubdomainField
            v-model="form.subdomain"
            :status="subdomainStatus"
            :reason="subdomainReason"
            :error="errors.subdomain"
            @update:model-value="clearFieldError('subdomain')"
            :tabindex="2"
        />

        <div class="grid gap-2">
            <Label for="founder_name">Tu nombre</Label>
            <Input
                id="founder_name"
                v-model="form.founder_name"
                type="text"
                autocomplete="name"
                :tabindex="3"
                placeholder="Ana Gómez"
                :aria-invalid="Boolean(errors.founder_name)"
                @input="clearFieldError('founder_name')"
            />
            <InputError :message="errors.founder_name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                :tabindex="4"
                placeholder="ana@eco.com"
                :aria-invalid="Boolean(errors.email)"
                @input="clearFieldError('email')"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="new-password"
                :tabindex="5"
                placeholder="Mínimo 8 caracteres"
                :aria-invalid="Boolean(errors.password)"
                @input="
                    clearFieldError('password');
                    clearFieldError('password_confirmation');
                "
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirmar contraseña</Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                autocomplete="new-password"
                :tabindex="6"
                placeholder="Repite la contraseña"
                :aria-invalid="Boolean(errors.password_confirmation)"
                @input="clearFieldError('password_confirmation')"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="mt-1 w-full"
            :tabindex="7"
            :disabled="processing"
            data-test="create-agency-button"
        >
            <Spinner v-if="processing" />
            Crear mi agencia
        </Button>

        <p class="text-center text-xs text-muted-foreground">
            Sin tarjeta de crédito · 14 días de prueba gratis
        </p>
    </form>
</template>
