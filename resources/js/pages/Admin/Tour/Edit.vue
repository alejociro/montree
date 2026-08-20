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
import TourDatesPanel from '@/components/organisms/TourDatesPanel.vue';
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
import type { ApiErrors } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type {
    SupportedCurrency,
    Tour,
    TourCategory,
    TourFormPayload,
    TourSubmitPayload,
    TourStatus as TourStatusType,
} from '@/types/tour';

const { t } = useTranslations();

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

function normalizePayload(data: TourFormPayload): TourSubmitPayload {
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
                toast.success(t('Cambios guardados.'));
                router.reload({ only: ['tour'] });
            },
            onError: (errors) => {
                form.setError(errors);
                toast.error(t('Revisa los campos marcados.'));
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
            return t('Publicar');
        case 'paused':
            return t('Pausar');
        case 'archived':
            return t('Archivar');
        case 'draft':
            return t('Volver a borrador');
        default:
            return status;
    }
}

const STATUS_ERROR_MESSAGES: Record<string, string> = {
    TOUR_NEEDS_IMAGE_TO_ACTIVATE: t(
        'El tour necesita al menos una imagen antes de activarse.',
    ),
    INVALID_STATUS_TRANSITION: t(
        'Ese cambio de estado no es válido desde el estado actual del tour.',
    ),
    TOUR_HAS_ACTIVE_BOOKINGS: t(
        'El tour tiene reservas activas: archívalo en lugar de cambiarlo de estado.',
    ),
    FEATURE_REQUIRES_ENTERPRISE: t(
        'Esta acción solo está disponible en el plan Enterprise.',
    ),
};

function statusErrorMessage(errors: ApiErrors): string {
    const mapped = errors.error_code
        ? STATUS_ERROR_MESSAGES[errors.error_code]
        : undefined;

    if (mapped) {
        return mapped;
    }

    if (!errors.error_code && errors._global) {
        return errors._global;
    }

    return t(
        'No se pudo cambiar el estado del tour. Revisa que tenga al menos una imagen y que el cambio sea válido desde su estado actual.',
    );
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
                toast.success(t('Estado actualizado.'));
                router.reload({ only: ['tour'] });
            },
            onError: (errors) => {
                statusError.value = statusErrorMessage(errors);
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
            t(
                '¿Eliminar este tour? Esta acción se puede revertir desde tu base de datos.',
            ),
        )
    ) {
        return;
    }

    void api.delete(destroyTour({ tour: props.tour.id }).url, {
        onSuccess: () => {
            toast.success(t('Tour eliminado.'));
            router.visit(indexPage().url);
        },
        onError: (errors) => {
            const code = errors.error_code;

            if (code === 'TOUR_HAS_ACTIVE_BOOKINGS') {
                toast.error(
                    t(
                        'No se puede eliminar: hay reservas activas. Archivalo en su lugar.',
                    ),
                );

                return;
            }

            toast.error(t('No se pudo eliminar el tour.'));
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
                {{ $t('Eliminar') }}
            </Button>
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <form class="space-y-8" @submit.prevent="submit">
                <TourForm
                    :model-value="form.data()"
                    :errors="formErrors"
                    :categories="props.categories"
                    @update:model-value="(value) => Object.assign(form, value)"
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
                        {{ saving ? $t('Guardando…') : $t('Guardar cambios') }}
                    </Button>
                    <span
                        v-if="form.isDirty && !saving"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('Tienes cambios sin guardar.') }}
                    </span>
                </div>
            </form>

            <aside class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            $t('Estado')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                $t(
                                    'Controla la visibilidad del tour en el catálogo.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Alert v-if="statusError" variant="destructive">
                            <AlertTitle>{{
                                $t('No se pudo cambiar el estado')
                            }}</AlertTitle>
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
                                {{
                                    $t(
                                        'No hay transiciones disponibles desde este estado.',
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </aside>
        </div>

        <div class="mt-8">
            <TourDatesPanel
                :tour-id="props.tour.id"
                :currency="props.tour.currency"
            />
        </div>
    </div>
</template>
