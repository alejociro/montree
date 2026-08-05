<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    SubdomainAvailabilityReason,
    SubdomainStatus,
} from '@/types/onboarding.types';

type Props = {
    modelValue: string;
    status: SubdomainStatus;
    reason: SubdomainAvailabilityReason;
    id?: string;
    label?: string;
    error?: string;
    domainSuffix?: string;
    tabindex?: number;
};

const props = withDefaults(defineProps<Props>(), {
    id: 'subdomain',
    label: 'Subdominio',
    error: undefined,
    domainSuffix: undefined,
    tabindex: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function resolveDefaultSuffix(): string {
    if (typeof window === 'undefined') {
        return '.montree.app';
    }

    return `.${window.location.host}`;
}

const suffix = computed(() => props.domainSuffix ?? resolveDefaultSuffix());

const messageId = computed(() => `${props.id}-status`);

function sanitize(raw: string): string {
    return raw
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '-')
        .replace(/-{2,}/g, '-');
}

function onInput(event: Event) {
    const target = event.target as HTMLInputElement;
    const clean = sanitize(target.value);

    if (clean !== target.value) {
        target.value = clean;
    }

    emit('update:modelValue', clean);
}

const statusMessage = computed(() => {
    if (props.status === 'checking') {
        return 'Verificando disponibilidad…';
    }

    if (props.status === 'available') {
        return '¡Disponible! Este subdominio es tuyo.';
    }

    if (props.status === 'unavailable') {
        switch (props.reason) {
            case 'taken':
                return 'Ese subdominio ya fue reclamado.';
            case 'reserved':
                return 'Ese subdominio está reservado, elegí otro.';
            case 'invalid_format':
                return 'Usá solo minúsculas, números y guiones.';
            default:
                return 'Ese subdominio no está disponible.';
        }
    }

    return 'Podés usar letras minúsculas, números y guiones.';
});

const statusTone = computed(() => {
    if (props.status === 'available') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (props.status === 'unavailable' || props.error) {
        return 'text-red-600 dark:text-red-500';
    }

    return 'text-muted-foreground';
});

const isInvalid = computed(
    () => props.status === 'unavailable' || Boolean(props.error),
);
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>

        <div
            class="flex items-stretch overflow-hidden rounded-md border bg-background shadow-xs transition-colors focus-within:ring-[3px] focus-within:ring-ring/50"
            :class="
                isInvalid
                    ? 'border-red-400 focus-within:border-red-500'
                    : status === 'available'
                      ? 'border-emerald-400 focus-within:border-emerald-500'
                      : 'border-input focus-within:border-ring'
            "
        >
            <input
                :id="id"
                :value="modelValue"
                type="text"
                inputmode="url"
                autocapitalize="none"
                autocomplete="off"
                spellcheck="false"
                :tabindex="tabindex"
                name="subdomain"
                placeholder="mi-agencia"
                class="w-full min-w-0 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                :aria-invalid="isInvalid"
                :aria-describedby="messageId"
                @input="onInput"
            />

            <span
                class="flex items-center border-l border-input bg-muted px-3 text-sm text-muted-foreground select-none"
            >
                {{ suffix }}
            </span>

            <span
                class="flex w-10 items-center justify-center"
                aria-hidden="true"
            >
                <Spinner v-if="status === 'checking'" class="size-4" />
                <Check
                    v-else-if="status === 'available'"
                    class="size-4 text-emerald-600 dark:text-emerald-400"
                />
                <X
                    v-else-if="status === 'unavailable'"
                    class="size-4 text-red-600 dark:text-red-500"
                />
            </span>
        </div>

        <p
            :id="messageId"
            class="text-xs"
            :class="statusTone"
            aria-live="polite"
        >
            {{ error ?? statusMessage }}
        </p>
    </div>
</template>
