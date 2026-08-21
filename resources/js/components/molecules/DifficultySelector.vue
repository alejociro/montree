<script setup lang="ts">
import { Activity, Flame, Mountain, Trees } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';
import type { TourDifficulty } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    modelValue: TourDifficulty;
    error?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourDifficulty): void;
}>();

type Option = {
    value: TourDifficulty;
    label: string;
    icon: typeof Trees;
    description: string;
};

const options = computed<Option[]>(() => [
    {
        value: 'easy',
        label: t('Fácil'),
        icon: Trees,
        description: t('Apto para todos'),
    },
    {
        value: 'moderate',
        label: t('Moderado'),
        icon: Activity,
        description: t('Condición media'),
    },
    {
        value: 'hard',
        label: t('Exigente'),
        icon: Mountain,
        description: t('Experiencia previa'),
    },
    {
        value: 'extreme',
        label: t('Extremo'),
        icon: Flame,
        description: t('Solo expertos'),
    },
]);

function select(value: TourDifficulty): void {
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="grid gap-2">
        <Label>{{ $t('Dificultad') }}</Label>
        <div
            class="grid grid-cols-2 gap-3 md:grid-cols-4"
            role="group"
            :aria-label="$t('Dificultad')"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :aria-pressed="props.modelValue === option.value"
                :class="
                    cn(
                        'flex flex-col items-start gap-1.5 rounded-lg border-2 p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        props.modelValue === option.value
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-input hover:border-primary/40 hover:bg-muted/40',
                    )
                "
                @click="select(option.value)"
            >
                <component :is="option.icon" class="size-5" />
                <span class="text-sm font-medium">{{ option.label }}</span>
                <span class="text-xs text-muted-foreground">{{
                    option.description
                }}</span>
            </button>
        </div>
        <InputError :message="error" />
    </div>
</template>
