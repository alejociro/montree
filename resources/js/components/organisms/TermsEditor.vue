<script setup lang="ts">
import { Info, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';

const TERMS_MAX_LENGTH = 20000;

type Props = {
    isDefault: boolean;
    /** T13: texto por defecto vigente; precarga el campo y alimenta "Restaurar". */
    defaultBody: string;
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

// T13: confirmación en la propia UI (sin `confirm()` del navegador) antes
// de reemplazar lo que el usuario haya escrito.
const confirmingRestore = ref(false);

function askRestore(): void {
    confirmingRestore.value = true;
}

function cancelRestore(): void {
    confirmingRestore.value = false;
}

function confirmRestore(): void {
    body.value = props.defaultBody;
    confirmingRestore.value = false;
}
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
                        'Este es el texto por defecto de Montree. Modifica lo que necesites y guarda; mientras no lo cambies, se actualiza solo cuando Montree mejore la plantilla.',
                    )
                }}
            </AlertDescription>
        </Alert>

        <Alert v-else>
            <Info class="size-4" />
            <AlertTitle>{{
                $t('Estás usando tus propios términos')
            }}</AlertTitle>
            <AlertDescription>
                {{
                    $t(
                        'Estos son tus propios términos y condiciones, distintos de los que ofrece Montree por defecto. Puedes restaurar el texto por defecto cuando quieras.',
                    )
                }}
            </AlertDescription>
        </Alert>

        <div class="grid gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Label for="terms-body">{{ $t('Tus términos') }}</Label>

                <div v-if="confirmingRestore" class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">
                        {{
                            $t(
                                '¿Reemplazar el texto actual por el por defecto?',
                            )
                        }}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        @click="confirmRestore"
                    >
                        {{ $t('Sí, restaurar') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="cancelRestore"
                    >
                        {{ $t('Cancelar') }}
                    </Button>
                </div>
                <Button
                    v-else
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="askRestore"
                >
                    <RotateCcw class="size-3.5" />
                    {{ $t('Restaurar texto por defecto') }}
                </Button>
            </div>

            <Textarea
                id="terms-body"
                v-model="body"
                class="[field-sizing:fixed] max-h-[65vh] min-h-80 overflow-y-auto font-mono text-sm"
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
