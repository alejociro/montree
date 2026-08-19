<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Leaf, MailCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { home, login } from '@/routes';
import { resendVerification } from '@/routes/onboarding';

const { t } = useTranslations();

type Props = {
    email: string | null;
    agencyName: string | null;
};

const props = defineProps<Props>();

const resending = ref(false);
const resent = ref(false);

const heading = computed(() =>
    props.agencyName
        ? `Revisa tu email para activar ${props.agencyName}`
        : t('Revisa tu email para activar tu agencia'),
);

function resend() {
    if (resending.value || !props.email) {
        return;
    }

    resending.value = true;

    router.post(
        resendVerification.url(),
        { email: props.email },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resent.value = true;
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
        <Head :title="$t('Revisa tu email — Montree')" />

        <Link :href="home().url" class="mb-10 inline-flex items-center gap-2">
            <span
                class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white"
            >
                <Leaf class="size-4" />
            </span>
            <span class="text-base font-semibold tracking-tight">{{
                $t('Montree')
            }}</span>
        </Link>

        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
            >
                <MailCheck class="size-8" />
            </div>

            <h1 class="text-2xl font-semibold tracking-tight">
                {{ heading }}
            </h1>

            <p class="mt-3 text-sm text-muted-foreground">
                {{ $t('Te enviamos un enlace de confirmación') }}
                <template v-if="email">
                    a
                    <span class="font-medium text-foreground">{{ email }}</span>
                </template>
                {{
                    $t(
                        '. Abrilo para activar tu cuenta y entrar directo a tu panel.',
                    )
                }}
            </p>

            <div
                v-if="resent"
                role="status"
                class="mt-6 flex items-center justify-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                <span>{{
                    $t('Si la cuenta existe, te reenviamos el email.')
                }}</span>
            </div>

            <div class="mt-8 space-y-3">
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('¿No lo recibiste? Revisa spam o vuelve a enviarlo.')
                    }}
                </p>
                <Button
                    v-if="email"
                    variant="outline"
                    class="w-full"
                    :disabled="resending"
                    data-test="resend-verification-button"
                    @click="resend"
                >
                    <Spinner v-if="resending" />
                    {{ $t('Reenviar email de verificación') }}
                </Button>
            </div>

            <p class="mt-8 text-center text-sm text-muted-foreground">
                {{ $t('¿Ya confirmaste?') }}
                <Link
                    :href="login().url"
                    class="font-medium text-emerald-600 underline-offset-4 hover:underline dark:text-emerald-400"
                >
                    {{ $t('Inicia sesión') }}
                </Link>
            </p>
        </div>
    </div>
</template>
