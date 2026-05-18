<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type MeetingPointValue = {
    meeting_point: string;
    meeting_latitude: string;
    meeting_longitude: string;
};

type Props = {
    modelValue: MeetingPointValue;
    errors?: Partial<Record<keyof MeetingPointValue, string | undefined>>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: MeetingPointValue): void;
}>();

function update<K extends keyof MeetingPointValue>(
    key: K,
    value: string,
): void {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>

<template>
    <section class="space-y-4">
        <Heading
            variant="small"
            title="Punto de encuentro"
            description="Dónde reciben a los viajeros."
        />

        <div class="grid gap-2">
            <Label for="meeting_point">Dirección o referencia</Label>
            <Input
                id="meeting_point"
                :model-value="modelValue.meeting_point"
                placeholder="Plaza Cocora, frente a la iglesia"
                @update:model-value="(v) => update('meeting_point', String(v))"
            />
            <InputError :message="errors?.meeting_point" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="meeting_latitude">Latitud</Label>
                <Input
                    id="meeting_latitude"
                    type="number"
                    step="0.0000001"
                    min="-90"
                    max="90"
                    :model-value="modelValue.meeting_latitude"
                    placeholder="4.6371"
                    @update:model-value="
                        (v) => update('meeting_latitude', String(v))
                    "
                />
                <InputError :message="errors?.meeting_latitude" />
            </div>

            <div class="grid gap-2">
                <Label for="meeting_longitude">Longitud</Label>
                <Input
                    id="meeting_longitude"
                    type="number"
                    step="0.0000001"
                    min="-180"
                    max="180"
                    :model-value="modelValue.meeting_longitude"
                    placeholder="-75.5096"
                    @update:model-value="
                        (v) => update('meeting_longitude', String(v))
                    "
                />
                <InputError :message="errors?.meeting_longitude" />
            </div>
        </div>

        <div
            v-if="modelValue.meeting_latitude && modelValue.meeting_longitude"
            class="flex items-center gap-2 rounded-md border border-dashed border-input bg-muted/30 p-3 text-sm text-muted-foreground"
        >
            <MapPin class="size-4" />
            Coordenadas listas. El mapa interactivo se mostrará a los viajeros.
        </div>
    </section>
</template>
