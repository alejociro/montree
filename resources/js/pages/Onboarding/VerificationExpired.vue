<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Leaf, MailWarning } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
import { resendVerification } from '@/routes/onboarding';

const email = ref('');
const emailError = ref<string | null>(null);
const resending = ref(false);
const resent = ref(false);

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function resend() {
    if (resending.value) {
        return;
    }

    emailError.value = null;

    if (!EMAIL_PATTERN.test(email.value)) {
        emailError.value = 'Ingresa un correo electrónico válido.';

        return;
    }

    resending.value = true;

    router.post(
        resendVerification.url(),
        { email: email.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resent.value = true;
            },
            onError: (errors) => {
                emailError.value =
                    errors.email ?? 'No pudimos reenviar el email.';
            },
            onFinish: () => {
                resending.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background px-6 py-12 text-foreground"
    >
        <Head title="Enlace expirado — Montree" />

        <Link :href="home().url" class="mb-10 inline-flex items-center gap-2">
            <span
                class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white"
            >
                <Leaf class="size-4" />
            </span>
            <span class="text-base font-semibold tracking-tight">Montree</span>
        </Link>

        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
            >
                <MailWarning class="size-8" />
            </div>

            <p
                class="text-xs font-semibold tracking-widest text-amber-600 uppercase dark:text-amber-400"
            >
                Enlace no válido
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                Este enlace de verificación expiró
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Los enlaces de confirmación caducan por seguridad. Ingresa tu
                correo y te enviamos uno nuevo.
            </p>

            <div
                v-if="resent"
                role="status"
                class="mt-6 flex items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                <span>Si la cuenta existe, te reenviamos el email.</span>
            </div>

            <form
                v-else
                class="mt-8 space-y-3 text-left"
                novalidate
                @submit.prevent="resend"
            >
                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        placeholder="tu@correo.com"
                        :aria-invalid="Boolean(emailError)"
                    />
                    <InputError :message="emailError ?? undefined" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="resending"
                    data-test="resend-verification-button"
                >
                    <Spinner v-if="resending" />
                    Reenviar email de verificación
                </Button>
            </form>

            <p class="mt-8 text-center text-sm text-muted-foreground">
                ¿Ya confirmaste?
                <Link
                    :href="login().url"
                    class="font-medium text-emerald-600 underline-offset-4 hover:underline dark:text-emerald-400"
                >
                    Inicia sesión
                </Link>
            </p>
        </div>
    </div>
</template>
