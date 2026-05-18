<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{ tourId: number; initialFavorite: boolean }>();

const isFavorite = ref(props.initialFavorite);
const submitting = ref(false);

function toggle() {
    if (submitting.value) {
        return;
    }
    submitting.value = true;
    const previous = isFavorite.value;
    isFavorite.value = !previous;
    router.post(
        '/api/v1/favorites',
        { tour_id: props.tourId },
        {
            preserveScroll: true,
            preserveState: true,
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
        @click="toggle"
    >
        <Heart :class="['size-4', isFavorite && 'fill-current text-destructive']" />
    </Button>
</template>
