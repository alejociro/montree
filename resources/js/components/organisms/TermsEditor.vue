<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';

const TERMS_MAX_LENGTH = 20000;

type Props = {
    isDefault: boolean;
    publicUrl: string;
    error?: string;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const body = defineModel<string>({ required: true });

const characterCount = computed(() => body.value.length);

const isOverLimit = computed(() => characterCount.value > TERMS_MAX_LENGTH);

const counterLabel = computed(() =>
    t(':count de :max caracteres', {
        count: characterCount.value,
        max: TERMS_MAX_LENGTH,
    }),
);

const publicLinkLabel = computed(() =>
    props.isDefault
        ? t('Ver el texto que se publica hoy')
        : t('Ver la página pública'),
);
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Términos y condiciones')"
            :description="
                $t(
                    'El documento que lee el viajero antes de reservar: cancelaciones, pagos, responsabilidades y datos del viajero.',
                )
            "
        />

        <Alert v-if="isDefault">
            <Info class="size-4" />
            <AlertTitle>{{
                $t('Estás usando el texto por defecto')
            }}</AlertTitle>
            <AlertDescription>
                {{
                    $t(
                        'Todavía rige el texto por defecto de Montree. Escribe los tuyos aquí para reemplazarlo; si dejas el campo vacío, vuelve el texto por defecto.',
                    )
                }}
            </AlertDescription>
        </Alert>

        <div class="grid gap-2">
            <Label for="terms-body">{{ $t('Tus términos') }}</Label>
            <Textarea
                id="terms-body"
                v-model="body"
                class="min-h-80 font-mono text-sm"
                :aria-invalid="Boolean(error) || isOverLimit"
                aria-describedby="terms-body-counter terms-body-help"
                :placeholder="
                    $t(
                        '## Cancelaciones — escribe aquí las condiciones de tu agencia',
                    )
                "
            />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p
                    id="terms-body-counter"
                    class="text-xs"
                    :class="
                        isOverLimit
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    {{ counterLabel }}
                </p>

                <a
                    :href="publicUrl"
                    target="_blank"
                    rel="noopener"
                    class="text-xs text-primary-readable underline underline-offset-2"
                >
                    {{ publicLinkLabel }}
                </a>
            </div>

            <InputError :message="error" />
        </div>

        <div
            id="terms-body-help"
            class="rounded-md border border-input bg-muted/40 p-4 text-xs text-muted-foreground"
        >
            <p class="font-medium text-foreground">
                {{ $t('Cómo dar formato') }}
            </p>
            <ul class="mt-2 space-y-1">
                <li>
                    <code class="font-mono">##</code>
                    {{ $t('al inicio de una línea crea un título.') }}
                </li>
                <li>
                    <code class="font-mono">**texto**</code>
                    {{ $t('pone el texto en negrita.') }}
                </li>
                <li>
                    <code class="font-mono">-</code>
                    {{
                        $t('al inicio de una línea crea un elemento de lista.')
                    }}
                </li>
            </ul>
            <p class="mt-2">
                {{
                    $t(
                        'El formato se aplica al guardar: ábrelo en la página pública para verlo como lo ve el viajero.',
                    )
                }}
            </p>
        </div>
    </section>
</template>
