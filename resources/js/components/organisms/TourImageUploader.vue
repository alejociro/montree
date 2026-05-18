<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, Loader2, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyImage,
    store as storeImage,
    update as updateImage,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TourImageController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { TourImage } from '@/types/tour';

type Props = {
    tourId: number;
    images: TourImage[];
};

const props = defineProps<Props>();

const fileInput = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const dragging = ref(false);

function openFilePicker(): void {
    fileInput.value?.click();
}

async function onFileSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        await upload(file);
    }

    input.value = '';
}

async function onDrop(event: DragEvent): Promise<void> {
    event.preventDefault();
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        await upload(file);
    }
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();
    dragging.value = true;
}

function onDragLeave(): void {
    dragging.value = false;
}

async function upload(file: File): Promise<void> {
    isUploading.value = true;

    const action = storeImage({ tour: props.tourId });
    const formData = new FormData();
    formData.append('image', file);

    try {
        await new Promise<void>((resolve, reject) => {
            router.post(action.url, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) => {
                    const message =
                        Object.values(errors)[0] ??
                        'No se pudo subir la imagen';
                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'No se pudo subir la imagen',
                    );
                    reject(new Error('upload-failed'));
                },
                onFinish: () => {
                    isUploading.value = false;
                },
            });
        });

        toast.success('Imagen subida.');
    } catch {
        // toast already shown in onError
    }
}

function setAsCover(image: TourImage): void {
    if (image.is_cover) {
        return;
    }

    const action = updateImage({ tour: props.tourId, image: image.id });
    router.patch(
        action.url,
        { is_cover: true },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Portada actualizada.'),
            onError: () => toast.error('No se pudo actualizar la portada.'),
        },
    );
}

function removeImage(image: TourImage): void {
    if (!confirm('¿Eliminar esta imagen?')) {
        return;
    }

    const action = destroyImage({ tour: props.tourId, image: image.id });
    router.delete(action.url, {
        preserveScroll: true,
        onSuccess: () => toast.success('Imagen eliminada.'),
        onError: () => toast.error('No se pudo eliminar la imagen.'),
    });
}
</script>

<template>
    <section class="space-y-4">
        <Heading
            variant="small"
            title="Galería"
            description="JPG, PNG o WebP. Máximo 5 MB por imagen."
        />

        <div
            :class="
                cn(
                    'flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                    dragging
                        ? 'border-primary bg-primary/5'
                        : 'border-input hover:border-primary/50',
                )
            "
            @drop="onDrop"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
        >
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="onFileSelected"
            />

            <Loader2
                v-if="isUploading"
                class="size-8 animate-spin text-primary"
            />
            <ImagePlus v-else class="size-8 text-muted-foreground" />

            <p class="text-sm text-muted-foreground">
                Arrastrá una imagen acá o usá el botón.
            </p>
            <Button
                type="button"
                variant="outline"
                :disabled="isUploading"
                @click="openFilePicker"
            >
                {{ isUploading ? 'Subiendo...' : 'Seleccionar imagen' }}
            </Button>
        </div>

        <div
            v-if="images.length > 0"
            class="grid grid-cols-2 gap-3 md:grid-cols-4"
        >
            <div
                v-for="image in images"
                :key="image.id"
                class="group relative aspect-square overflow-hidden rounded-lg border border-input"
            >
                <img
                    :src="image.url"
                    :alt="image.alt_text ?? ''"
                    class="size-full object-cover"
                />

                <div
                    v-if="image.is_cover"
                    class="absolute top-2 left-2 flex items-center gap-1 rounded-md bg-primary px-2 py-1 text-xs font-medium text-primary-foreground"
                >
                    <Star class="size-3" />
                    Portada
                </div>

                <div
                    class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition-opacity group-hover:opacity-100"
                >
                    <Button
                        v-if="!image.is_cover"
                        type="button"
                        size="sm"
                        variant="secondary"
                        class="h-7 px-2 text-xs"
                        @click="setAsCover(image)"
                    >
                        <Star class="size-3" />
                        Portada
                    </Button>
                    <span v-else />
                    <Button
                        type="button"
                        size="icon"
                        variant="destructive"
                        class="size-7"
                        @click="removeImage(image)"
                    >
                        <Trash2 class="size-3" />
                    </Button>
                </div>
            </div>
        </div>

        <p v-else class="text-xs text-muted-foreground">
            Aún no hay imágenes. Subí al menos una para activar el tour.
        </p>
    </section>
</template>
