<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    min: number | null;
    max: number | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:min', value: number | null): void;
    (event: 'update:max', value: number | null): void;
}>();

const minModel = computed({
    get: () => (props.min === null ? '' : String(props.min)),
    set: (value: string) => emit('update:min', parseValue(value)),
});

const maxModel = computed({
    get: () => (props.max === null ? '' : String(props.max)),
    set: (value: string) => emit('update:max', parseValue(value)),
});

function parseValue(input: string): number | null {
    if (input.trim() === '') {
        return null;
    }

    const value = Number(input);

    return Number.isFinite(value) && value >= 0 ? value : null;
}
</script>

<template>
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium text-foreground">
            Rango de precio
        </legend>
        <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
                <Label
                    for="catalog-price-min"
                    class="text-xs text-muted-foreground"
                    >Mínimo</Label
                >
                <Input
                    id="catalog-price-min"
                    v-model="minModel"
                    type="number"
                    min="0"
                    placeholder="0"
                    inputmode="numeric"
                />
            </div>
            <div class="space-y-1">
                <Label
                    for="catalog-price-max"
                    class="text-xs text-muted-foreground"
                    >Máximo</Label
                >
                <Input
                    id="catalog-price-max"
                    v-model="maxModel"
                    type="number"
                    min="0"
                    placeholder="Sin tope"
                    inputmode="numeric"
                />
            </div>
        </div>
    </fieldset>
</template>
