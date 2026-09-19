<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import CategoryGlyph from '@/components/atoms/CategoryGlyph.vue';
import InputError from '@/components/InputError.vue';
import ImageUploadField from '@/components/molecules/ImageUploadField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import type {
    AdminCategory,
    CategoryIcon,
    CategoryIconOption,
} from '@/types/category';

const { t } = useTranslations();

/**
 * Alta y edición de una categoría. Ícono e imagen conviven: se puede elegir un
 * ícono y subir una imagen, y manda la imagen. El aviso del selector lo dice
 * para que nadie crea que su ícono se perdió.
 */
type Props = {
    open: boolean;
    /** `null` crea una categoría nueva. */
    category: AdminCategory | null;
    icons: CategoryIconOption[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved', created: boolean): void;
}>();

type CategoryFormState = {
    name: string;
    description: string;
    icon: CategoryIcon | null;
    image: File | null;
    remove_image: boolean;
    is_active: boolean;
};

function blankState(category: AdminCategory | null): CategoryFormState {
    return {
        name: category?.name ?? '',
        description: category?.description ?? '',
        icon: category?.icon ?? null,
        image: null,
        remove_image: false,
        is_active: category?.is_active ?? true,
    };
}

const form = useForm<CategoryFormState>(blankState(null));

watch(
    () => [props.open, props.category] as const,
    ([open, category]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        Object.assign(form, blankState(category));
    },
    { immediate: true },
);

const isEditing = computed(() => props.category !== null);

const currentImageUrl = computed(() => props.category?.image_url ?? null);

const imageWins = computed(
    () => form.image !== null || (currentImageUrl.value !== null && !form.remove_image),
);

function chooseIcon(icon: CategoryIcon): void {
    form.icon = form.icon === icon ? null : icon;
}

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    const category = props.category;
    const created = category === null;

    // Multipart no viaja en PUT: se manda por POST con el método suplantado.
    form.transform((data) => ({
        ...data,
        remove_image: data.remove_image ? 1 : 0,
        is_active: data.is_active ? 1 : 0,
        ...(created ? {} : { _method: 'put' }),
    })).post(
        created
            ? CategoryController.store().url
            : CategoryController.update(category.id).url,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => emit('saved', created),
            onError: () => toast.error(t('Revisa los campos marcados.')),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? $t('Editar categoría') : $t('Nueva categoría') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Las categorías agrupan tus productos en el catálogo y en los filtros.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="category-name">{{ $t('Nombre') }}</Label>
                    <Input
                        id="category-name"
                        v-model="form.name"
                        :placeholder="$t('Senderismo')"
                        maxlength="80"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-description">
                        {{ $t('Descripción') }}
                    </Label>
                    <Textarea
                        id="category-description"
                        v-model="form.description"
                        rows="2"
                        maxlength="500"
                        :placeholder="
                            $t('Qué tipo de experiencias reúne esta categoría')
                        "
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('Ícono') }}</Label>
                    <div class="grid grid-cols-8 gap-1.5">
                        <button
                            v-for="option in icons"
                            :key="option.value"
                            type="button"
                            :title="option.label"
                            :aria-label="option.label"
                            :aria-pressed="form.icon === option.value"
                            class="relative grid aspect-square place-items-center rounded-lg border transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                form.icon === option.value
                                    ? 'border-primary bg-primary-soft text-primary-readable'
                                    : 'border-border text-muted-foreground hover:border-primary/50'
                            "
                            @click="chooseIcon(option.value)"
                        >
                            <CategoryGlyph
                                :name="option.label"
                                :icon="option.value"
                                icon-class="size-4.5"
                            />
                            <Check
                                v-if="form.icon === option.value"
                                class="absolute top-0.5 right-0.5 size-3"
                            />
                        </button>
                    </div>
                    <InputError :message="form.errors.icon" />
                </div>

                <ImageUploadField
                    id="category-image"
                    v-model="form.image"
                    v-model:removed="form.remove_image"
                    :label="$t('Imagen')"
                    accept="image/png,image/jpeg,image/svg+xml,image/webp"
                    :hint="$t('PNG, JPG, SVG o WEBP de hasta 1 MB.')"
                    :current-url="currentImageUrl"
                    preview-class="size-16"
                    removable
                    :error="form.errors.image"
                />

                <p v-if="imageWins" class="-mt-3 text-xs text-muted-foreground">
                    {{ $t('Mientras haya imagen, se muestra la imagen y no el ícono.') }}
                </p>

                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-0.5">
                        <Label for="category-active">{{ $t('Activa') }}</Label>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(
                                    'Una categoría inactiva desaparece del catálogo y de los filtros, pero los productos la conservan.',
                                )
                            }}
                        </p>
                    </div>
                    <Switch
                        id="category-active"
                        :model-value="form.is_active"
                        @update:model-value="(value) => (form.is_active = value)"
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? $t('Guardar cambios') : $t('Crear categoría') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
