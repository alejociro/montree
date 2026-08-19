<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { toRef } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Onboarding/AgencyOnboardingController';
import InputError from '@/components/InputError.vue';
import SubdomainField from '@/components/molecules/SubdomainField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useSubdomainAvailability } from '@/composables/useSubdomainAvailability';

// WHY: no client-side mirror of the rules — RegisterAgencyRequest is the single
// source of truth and its errors come back on the redirect. The typeahead below
// is a different mechanism: live feedback while typing, not validation.
const form = useForm({
    agency_name: '',
    subdomain: '',
    founder_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const { status: subdomainStatus, reason: subdomainReason } =
    useSubdomainAvailability(toRef(form, 'subdomain'));

function submit() {
    form.submit(store(), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="agency_name">{{ $t('Nombre de la agencia') }}</Label>
            <Input
                id="agency_name"
                v-model="form.agency_name"
                type="text"
                autocomplete="organization"
                :tabindex="1"
                :placeholder="$t('Eco Adventures')"
                :aria-invalid="Boolean(form.errors.agency_name)"
                @input="form.clearErrors('agency_name')"
            />
            <InputError :message="form.errors.agency_name" />
        </div>

        <SubdomainField
            v-model="form.subdomain"
            :status="subdomainStatus"
            :reason="subdomainReason"
            :error="form.errors.subdomain"
            @update:model-value="form.clearErrors('subdomain')"
            :tabindex="2"
        />

        <div class="grid gap-2">
            <Label for="founder_name">{{ $t('Tu nombre') }}</Label>
            <Input
                id="founder_name"
                v-model="form.founder_name"
                type="text"
                autocomplete="name"
                :tabindex="3"
                :placeholder="$t('Ana Gómez')"
                :aria-invalid="Boolean(form.errors.founder_name)"
                @input="form.clearErrors('founder_name')"
            />
            <InputError :message="form.errors.founder_name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">{{ $t('Correo electrónico') }}</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                :tabindex="4"
                :placeholder="$t('ana@eco.com')"
                :aria-invalid="Boolean(form.errors.email)"
                @input="form.clearErrors('email')"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">{{ $t('Contraseña') }}</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="new-password"
                :tabindex="5"
                :placeholder="$t('Mínimo 8 caracteres')"
                :aria-invalid="Boolean(form.errors.password)"
                @input="form.clearErrors('password', 'password_confirmation')"
            />
            <InputError :message="form.errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{
                $t('Confirmar contraseña')
            }}</Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                autocomplete="new-password"
                :tabindex="6"
                :placeholder="$t('Repite la contraseña')"
                :aria-invalid="Boolean(form.errors.password_confirmation)"
                @input="form.clearErrors('password_confirmation')"
            />
            <InputError :message="form.errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="mt-1 w-full"
            :tabindex="7"
            :disabled="form.processing"
            data-test="create-agency-button"
        >
            <Spinner v-if="form.processing" />
            {{ $t('Crear mi agencia') }}
        </Button>

        <p class="text-center text-xs text-muted-foreground">
            {{ $t('Sin tarjeta de crédito · 14 días de prueba gratis') }}
        </p>
    </form>
</template>
