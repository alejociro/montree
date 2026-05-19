<script setup lang="ts">
import { Heart } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';

const props = defineProps<{ tourId: number; initialFavorite: boolean }>();

const isFavorite = ref(props.initialFavorite);
const submitting = ref(false);
const api = useApi();

const ariaLabel = computed(() =>
    isFavorite.value ? 'Quitar de favoritos' : 'Agregar a favoritos',
);

function toggle() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    const previous = isFavorite.value;
    isFavorite.value = !previous;

    void api.post(
        '/api/v1/favorites',
        { tour_id: props.tourId },
        {
            onError: () => {
                isFavorite.value = previous;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="icon"
        :aria-pressed="isFavorite"
        :aria-label="ariaLabel"
        @click="toggle"
    >
        <Heart
            :class="['size-4', isFavorite && 'fill-current text-destructive']"
        />
    </Button>
</template>
