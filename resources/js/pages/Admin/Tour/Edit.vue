<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { index as indexPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import {
    destroy as destroyTour,
    update as updateTour,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import changeStatus from '@/actions/App/Http/Controllers/Api/V1/Admin/TourStatusController';
import Heading from '@/components/Heading.vue';
import TourForm from '@/components/organisms/TourForm.vue';
import TourImageUploader from '@/components/organisms/TourImageUploader.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useApi } from '@/composables/useApi';
import type {
    SupportedCurrency,
    Tour,
    TourCategory,
    TourFormPayload,
    TourStatus as TourStatusType,
} from '@/types/tour';

const api = useApi();

type Props = {
    tour: Tour;
    categories: TourCategory[];
};

const props = defineProps<Props>();
const statusError = ref<string | null>(null);
const changingStatus = ref(false);

const initialValues = computed<TourFormPayload>(() => ({
    name: props.tour.name,
    short_description: props.tour.short_description ?? '',
    description: props.tour.description ?? '',
    category_id: props.tour.category_id,
    base_price: props.tour.base_price,
    currency: props.tour.currency as SupportedCurrency,
    duration_hours: props.tour.duration_hours,
    difficulty: props.tour.difficulty,
    default_capacity: props.tour.default_capacity,
    meeting_point: props.tour.meeting_point ?? '',
    meeting_latitude: props.tour.meeting_latitude ?? '',
    meeting_longitude: props.tour.meeting_longitude ?? '',
    includes: props.tour.includes ?? [],
    excludes: props.tour.excludes ?? [],
    requirements: props.tour.requirements ?? [],
    itinerary: (props.tour.itinerary ?? []).map((step) => ({
        step_number: step.step_number,
        title: step.title,
        description: step.description ?? '',
        duration_label: step.duration_label ?? '',
    })),
}));

const form = useForm<TourFormPayload>(() => ({ ...initialValues.value }));
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const saving = ref(false);

function normalizePayload(data: TourFormPayload): TourFormPayload {
    return {
        ...data,
        meeting_latitude:
            data.meeting_latitude === '' ? null : data.meeting_latitude,
        meeting_longitude:
            data.meeting_longitude === '' ? null : data.meeting_longitude,
        meeting_point: data.meeting_point === '' ? null : data.meeting_point,
        short_description:
            data.short_description === '' ? null : data.short_description,
    };
}

function submit(): void {
    form.clearErrors();
    saving.value = true;

    void api.put(
        updateTour({ tour: props.tour.id }).url,
        normalizePayload(form.data()),
        {
            onSuccess: () => {
                toast.success('Cambios guardados.');
                router.reload({ only: ['tour'] });
            },
            onError: (errors) => {
                form.setError(errors);
                toast.error('Revisá los campos marcados.');
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

const allowedNextStatuses = computed<TourStatusType[]>(() => {
    switch (props.tour.status) {
        case 'draft':
            return ['active', 'archived'];
        case 'active':
            return ['paused', 'archived'];
        case 'paused':
            return ['active', 'archived'];
        case 'archived':
            return ['draft'];
        default:
            return [];
    }
});

function statusLabel(status: TourStatusType): string {
    switch (status) {
        case 'active':
            return 'Publicar';
        case 'paused':
            return 'Pausar';
        case 'archived':
            return 'Archivar';
        case 'draft':
            return 'Volver a borrador';
        default:
            return status;
    }
}

function transitionTo(next: TourStatusType): void {
    statusError.value = null;
    changingStatus.value = true;

    const action = changeStatus({ tour: props.tour.id });

    void api.patch(
        action.url,
        { status: next },
        {
            onSuccess: () => {
                toast.success('Estado actualizado.');
                router.reload({ only: ['tour'] });
            },
            onError: (errors) => {
                const code = errors.error_code;

                if (code === 'TOUR_NEEDS_IMAGE_TO_ACTIVATE') {
                    statusError.value =
                        'El tour necesita al menos una imagen antes de activarse.';
                } else if (code === 'TOUR_NEEDS_FUTURE_DATE_TO_ACTIVATE') {
                    statusError.value =
                        'El tour necesita al menos una fecha futura abierta para activarse.';
                } else {
                    statusError.value = 'No se pudo cambiar el estado.';
                }
            },
            onFinish: () => {
                changingStatus.value = false;
            },
        },
    );
}

function deleteTour(): void {
    if (
        !confirm(
            '¿Eliminar este tour? Esta acción se puede revertir desde tu base de datos.',
        )
    ) {
        return;
    }

    void api.delete(destroyTour({ tour: props.tour.id }).url, {
        onSuccess: () => {
            toast.success('Tour eliminado.');
            router.visit(indexPage().url);
        },
        onError: (errors) => {
            const code = errors.error_code;

            if (code === 'TOUR_HAS_ACTIVE_BOOKINGS') {
                toast.error(
                    'No se puede eliminar: hay reservas activas. Archivalo en su lugar.',
                );

                return;
            }

            toast.error('No se pudo eliminar el tour.');
        },
    });
}
</script>

<template>
    <Head :title="`Editar: ${props.tour.name}`" />

    <div class="px-4 py-6 md:px-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link :href="indexPage().url">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="size-4" />
                    </Button>
                </Link>
                <div>
                    <Heading :title="props.tour.name" />
                    <div class="mt-1 flex items-center gap-2">
                        <TourStatusBadge :status="props.tour.status" />
                        <span class="text-xs text-muted-foreground"
                            >/{{ props.tour.slug }}</span
                        >
                    </div>
                </div>
            </div>

            <Button
                variant="ghost"
                :disabled="changingStatus"
                @click="deleteTour"
            >
                <Trash2 class="size-4 text-destructive" />
                Eliminar
            </Button>
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <form class="space-y-8" @submit.prevent="submit">
                <TourForm
                    v-model="form"
                    :errors="formErrors"
                    :categories="props.categories"
                />

                <Card>
                    <CardContent class="p-6">
                        <TourImageUploader
                            :tour-id="props.tour.id"
                            :images="props.tour.images"
                        />
                    </CardContent>
                </Card>

                <div class="flex items-center gap-3 border-t border-input pt-6">
                    <Button type="submit" :disabled="saving">
                        {{ saving ? 'Guardando…' : 'Guardar cambios' }}
                    </Button>
                    <span
                        v-if="form.isDirty && !saving"
                        class="text-xs text-muted-foreground"
                    >
                        Tenés cambios sin guardar.
                    </span>
                </div>
            </form>

            <aside class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Estado</CardTitle>
                        <CardDescription>
                            Controlá la visibilidad del tour en el catálogo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Alert v-if="statusError" variant="destructive">
                            <AlertTitle
                                >No se pudo cambiar el estado</AlertTitle
                            >
                            <AlertDescription>{{
                                statusError
                            }}</AlertDescription>
                        </Alert>

                        <div class="flex flex-col gap-2">
                            <Button
                                v-for="next in allowedNextStatuses"
                                :key="next"
                                type="button"
                                size="sm"
                                :variant="
                                    next === 'archived' ? 'outline' : 'default'
                                "
                                :disabled="changingStatus"
                                @click="transitionTo(next)"
                            >
                                <Loader2
                                    v-if="changingStatus"
                                    class="size-4 animate-spin"
                                />
                                {{ statusLabel(next) }}
                            </Button>
                            <p
                                v-if="allowedNextStatuses.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                No hay transiciones disponibles desde este
                                estado.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </aside>
        </div>
    </div>
</template>
