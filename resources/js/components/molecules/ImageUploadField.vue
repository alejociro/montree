<script setup lang="ts">
import { Trash2, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    accept: string;
    hint?: string;
    currentUrl?: string | null;
    previewClass?: string;
    removable?: boolean;
    removed?: boolean;
    error?: string;
};

const props = withDefaults(defineProps<Props>(), {
    hint: undefined,
    currentUrl: null,
    previewClass: 'h-12 w-auto',
    removable: false,
    removed: false,
    error: undefined,
});

const file = defineModel<File | null>({ required: true });

const emit = defineEmits<{
    (e: 'update:removed', value: boolean): void;
}>();

const objectUrl = ref<string | null>(null);

watch(
    file,
    (value) => {
        if (objectUrl.value !== null) {
            URL.revokeObjectURL(objectUrl.value);
        }

        objectUrl.value = value ? URL.createObjectURL(value) : null;
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (objectUrl.value !== null) {
        URL.revokeObjectURL(objectUrl.value);
    }
});

const previewUrl = computed(() => {
    if (objectUrl.value !== null) {
        return objectUrl.value;
    }

    return props.removed ? null : (props.currentUrl ?? null);
});

const canRemove = computed(
    () => props.removable && !props.removed && previewUrl.value !== null,
);

function onChange(event: Event): void {
    const input = event.target as HTMLInputElement;

    file.value = input.files?.[0] ?? null;

    if (file.value !== null) {
        emit('update:removed', false);
    }
}

function remove(): void {
    file.value = null;
    emit('update:removed', true);
}
</script>

<template>
    <div class="grid content-start gap-2">
        <Label :for="id">{{ label }}</Label>

        <img
            v-if="previewUrl"
            :src="previewUrl"
            :alt="label"
            :class="[
                'rounded border border-input bg-muted object-contain p-1',
                previewClass,
            ]"
        />

        <label
            class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-input px-3 py-2 text-sm text-muted-foreground transition hover:border-ring hover:text-foreground"
        >
            <Upload class="size-4 shrink-0" />
            <span class="truncate">
                {{ file ? file.name : $t('Seleccionar archivo') }}
            </span>
            <input
                :id="id"
                type="file"
                :accept="accept"
                class="hidden"
                @change="onChange"
            />
        </label>

        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>

        <p v-if="removed" class="text-xs text-muted-foreground">
            {{ $t('Se eliminará al guardar.') }}
        </p>

        <Button
            v-if="canRemove"
            type="button"
            variant="ghost"
            size="sm"
            class="justify-self-start"
            @click="remove"
        >
            <Trash2 class="size-4" />
            {{ $t('Quitar') }}
        </Button>

        <InputError :message="error" />
    </div>
</template>
