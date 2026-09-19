<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import ReorderCategoriesController from '@/actions/App/Http/Controllers/Admin/ReorderCategoriesController';
import CategoryGlyph from '@/components/atoms/CategoryGlyph.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import CategoryDialog from '@/components/organisms/CategoryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import type { AdminCategory, CategoryIconOption } from '@/types/category';

const { t } = useTranslations();

const page = usePage();

type Props = {
    categories: AdminCategory[];
    icons: CategoryIconOption[];
};

const props = defineProps<Props>();

/**
 * Copia local del orden: arrastrar tiene que moverse con el dedo y recién
 * después avisarle al servidor. Cada respuesta del servidor la vuelve a mandar.
 */
const ordered = ref<AdminCategory[]>([...props.categories]);

watch(
    () => props.categories,
    (categories) => (ordered.value = [...categories]),
);

const dialogOpen = ref(false);
const editing = ref<AdminCategory | null>(null);
const removing = ref<AdminCategory | null>(null);
const dragging = ref<number | null>(null);

const RELOAD_CATEGORIES = {
    preserveScroll: true,
    only: ['categories'],
};

const activeCount = computed(
    () => ordered.value.filter((category) => category.is_active).length,
);

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(category: AdminCategory): void {
    editing.value = category;
    dialogOpen.value = true;
}

function onSaved(created: boolean): void {
    dialogOpen.value = false;
    editing.value = null;

    toast.success(
        page.props.flash.success ??
            t(created ? 'Categoría creada.' : 'Categoría actualizada.'),
    );

    router.reload(RELOAD_CATEGORIES);
}

function confirmRemove(): void {
    const category = removing.value;

    if (category === null) {
        return;
    }

    router.delete(CategoryController.destroy(category.id).url, {
        ...RELOAD_CATEGORIES,
        onSuccess: () => {
            removing.value = null;
            toast.success(
                page.props.flash.success ?? t('Categoría eliminada.'),
            );
        },
        onError: (errors) => {
            removing.value = null;
            toast.error(errors.category || t('No se pudo eliminar.'));
        },
    });
}

function move(from: number, to: number): void {
    if (to < 0 || to >= ordered.value.length || from === to) {
        return;
    }

    const next = [...ordered.value];
    const [moved] = next.splice(from, 1);

    if (moved === undefined) {
        return;
    }

    next.splice(to, 0, moved);
    ordered.value = next;

    persistOrder();
}

function persistOrder(): void {
    router.patch(
        ReorderCategoriesController.url(),
        { ids: ordered.value.map((category) => category.id) },
        {
            ...RELOAD_CATEGORIES,
            preserveState: true,
            onError: () => toast.error(t('No se pudo guardar el orden.')),
        },
    );
}

function onDrop(index: number): void {
    const from = dragging.value;
    dragging.value = null;

    if (from !== null) {
        move(from, index);
    }
}
</script>

<template>
    <Head :title="$t('Categorías')" />

    <div class="px-4 py-6 md:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Categorías')"
                :description="
                    $t(
                        'Agrupan tus productos en el catálogo. El orden de esta lista es el que ve el viajero.',
                    )
                "
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                {{ $t('Nueva categoría') }}
            </Button>
        </div>

        <MonoLabel class="mt-5 block">
            {{
                $t(':active activas de :total', {
                    active: activeCount,
                    total: ordered.length,
                })
            }}
        </MonoLabel>

        <ul class="mt-3 grid gap-2">
            <li
                v-for="(category, index) in ordered"
                :key="category.id"
                class="flex items-center gap-3 rounded-2xl border border-border bg-card p-3 transition"
                :class="dragging === index ? 'opacity-60' : ''"
                draggable="true"
                @dragstart="dragging = index"
                @dragend="dragging = null"
                @dragover.prevent
                @drop.prevent="onDrop(index)"
            >
                <GripVertical
                    class="size-4 shrink-0 cursor-grab text-muted-foreground"
                    aria-hidden="true"
                />

                <span
                    class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-soft text-primary-readable"
                >
                    <CategoryGlyph
                        :name="category.name"
                        :icon="category.icon"
                        :image-url="category.image_url"
                        :class="category.image_url ? 'size-11' : undefined"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="truncate text-[15px] font-semibold">
                            {{ category.name }}
                        </h2>
                        <Badge v-if="!category.is_active" variant="secondary">
                            {{ $t('Inactiva') }}
                        </Badge>
                    </div>
                    <p
                        v-if="category.description"
                        class="line-clamp-1 text-[13px] text-muted-foreground"
                    >
                        {{ category.description }}
                    </p>
                </div>

                <MonoLabel class="hidden shrink-0 sm:block">
                    {{
                        $tc(
                            ':count producto|:count productos',
                            category.tours_count,
                        )
                    }}
                </MonoLabel>

                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="index === 0"
                        :aria-label="$t('Subir :name', { name: category.name })"
                        @click="move(index, index - 1)"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="index === ordered.length - 1"
                        :aria-label="$t('Bajar :name', { name: category.name })"
                        @click="move(index, index + 1)"
                    >
                        <ArrowDown class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Editar :name', { name: category.name })"
                        @click="openEdit(category)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="
                            $t('Eliminar :name', { name: category.name })
                        "
                        @click="removing = category"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <button
            v-if="ordered.length === 0"
            type="button"
            class="mt-3 flex min-h-[140px] w-full flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-input p-6 text-center transition hover:border-primary hover:bg-primary-soft/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @click="openCreate"
        >
            <Plus class="size-5 text-muted-foreground" />
            <span class="text-sm font-medium">{{ $t('Nueva categoría') }}</span>
            <span class="max-w-[38ch] text-xs text-muted-foreground">
                {{
                    $t(
                        'Aún no tienes categorías: sin ellas el catálogo no ofrece filtros.',
                    )
                }}
            </span>
        </button>

        <CategoryDialog
            v-model:open="dialogOpen"
            :category="editing"
            :icons="props.icons"
            @saved="onSaved"
        />

        <Dialog
            :open="removing !== null"
            @update:open="(value) => !value && (removing = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Eliminar categoría') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                '":name" se eliminará de forma permanente. Si tiene productos, desactívala en vez de borrarla.',
                                { name: removing?.name ?? '' },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="removing = null">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button variant="destructive" @click="confirmRemove">
                        {{ $t('Eliminar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
