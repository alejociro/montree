<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatDate } from '@/lib/format';

defineOptions({ layout: PublicLayout });

type Props = {
    terms: {
        html: string;
        is_default: boolean;
        updated_at: string | null;
    };
    agency: {
        name: string;
    };
};

const props = defineProps<Props>();

const lastUpdated = computed(() =>
    props.terms.updated_at ? formatDate(props.terms.updated_at) : null,
);
</script>

<template>
    <div class="bg-background">
        <Head
            :title="
                $t('Términos y condiciones — :agency', { agency: agency.name })
            "
        />

        <div class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 lg:py-16">
            <header class="border-b border-border/60 pb-6">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ $t('Legal') }}
                </p>
                <h1
                    class="mt-2 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
                >
                    {{ $t('Términos y condiciones') }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ agency.name }}
                </p>
                <p
                    v-if="lastUpdated"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{
                        $t('Última actualización: :date', { date: lastUpdated })
                    }}
                </p>
            </header>

            <!-- eslint-disable-next-line vue/no-v-html -- El HTML llega convertido y saneado por el servidor (`html_input => strip`); volver a procesarlo en el cliente no agrega seguridad. -->
            <article
                class="mt-8 text-base leading-relaxed text-foreground [&_a]:text-primary-readable [&_a]:underline [&_a]:underline-offset-2 [&_a:hover]:text-primary [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2:first-child]:mt-0 [&_h3]:mt-8 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_li]:mt-1.5 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-4 [&_strong]:font-semibold [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6"
                v-html="terms.html"
            />
        </div>
    </div>
</template>
