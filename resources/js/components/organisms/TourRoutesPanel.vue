<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DefaultRouteController from '@/actions/App/Http/Controllers/Admin/DefaultRouteController';
import TourRouteController from '@/actions/App/Http/Controllers/Admin/TourRouteController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import LogisticsRecordDialog from '@/components/organisms/LogisticsRecordDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { factsFor, localityOf } from '@/lib/logistics';
import type { RouteResource } from '@/types/logistics';

const { t } = useTranslations();

const page = usePage();

/**
 * Las rutas son del producto: se crean, se editan y se borran desde su ficha.
 * La que quede marcada es la que propone cada salida nueva.
 */
type Props = {
    tourId: number;
    routes: RouteResource[];
};

const props = defineProps<Props>();

const dialogOpen = ref(false);
const editing = ref<RouteResource | null>(null);

const RELOAD_ROUTES = {
    preserveScroll: true,
    only: ['tour'],
};

function dialogAction(): { url: string; method: 'post' | 'put' } {
    const route = editing.value;

    return route === null
        ? { url: TourRouteController.store(props.tourId).url, method: 'post' }
        : { url: TourRouteController.update(route.id).url, method: 'put' };
}

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(route: RouteResource): void {
    editing.value = route;
    dialogOpen.value = true;
}

function onSaved(): void {
    const created = editing.value === null;

    dialogOpen.value = false;
    editing.value = null;

    toast.success(
        page.props.flash.success ??
            t(created ? 'Ruta creada.' : 'Ruta actualizada.'),
    );

    router.reload(RELOAD_ROUTES);
}

function makeDefault(route: RouteResource): void {
    router.patch(DefaultRouteController.url(route.id), {}, RELOAD_ROUTES);
}

function remove(route: RouteResource): void {
    if (!confirm(t('¿Eliminar ":name"?', { name: route.name }))) {
        return;
    }

    router.delete(TourRouteController.destroy(route.id).url, {
        ...RELOAD_ROUTES,
        onError: (errors) => {
            toast.error(errors.route || t('No se pudo eliminar.'));
        },
    });
}
</script>

<template>
    <div>
            <Card id="tour-block-routes" class="scroll-mt-24">
            <CardHeader>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle>{{ $t('Rutas del producto') }}</CardTitle>
                        <CardDescription>{{
                            $t(
                                'Cada salida opera una de estas rutas. La predeterminada es la que se propone al crear una salida.',
                            )
                        }}</CardDescription>
                    </div>
                    <Button type="button" variant="outline" @click="openCreate">
                        <Plus class="size-4" />
                        {{ $t('Nueva ruta') }}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <p
                    v-if="props.routes.length === 0"
                    class="rounded-xl border border-dashed border-input px-6 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ $t('Aún no tienes rutas') }}
                </p>

                <ul v-else class="grid gap-3.5 md:grid-cols-2">
                    <li
                        v-for="route in props.routes"
                        :key="route.id"
                        class="flex flex-col rounded-2xl border border-border bg-card p-4"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-[38px] shrink-0 place-items-center rounded-xl bg-primary-soft text-primary-readable"
                            >
                                <MapPin class="size-4.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-[15px] font-semibold">
                                    {{ route.name }}
                                </h3>
                                <p
                                    v-if="localityOf(route)"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ localityOf(route) }}
                                </p>
                            </div>
                            <MonoLabel v-if="route.is_default" class="shrink-0">
                                {{ $t('Predeterminada') }}
                            </MonoLabel>
                            <ActionMenu
                                variant="ghost"
                                :label="
                                    $t('Acciones de :name', { name: route.name })
                                "
                            >
                                <DropdownMenuItem @select="openEdit(route)">
                                    <Pencil class="size-4" />
                                    {{ $t('Editar ficha') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="!route.is_default"
                                    @select="makeDefault(route)"
                                >
                                    <Star class="size-4" />
                                    {{ $t('Marcar como predeterminada') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @select="remove(route)"
                                >
                                    <Trash2 class="size-4" />
                                    {{ $t('Eliminar') }}
                                </DropdownMenuItem>
                            </ActionMenu>
                        </div>

                        <dl class="mt-3 grid gap-1.5 text-xs">
                            <div
                                v-for="fact in factsFor('routes', route)"
                                :key="fact.label"
                                class="flex justify-between gap-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ fact.label }}
                                </dt>
                                <dd class="truncate font-medium">
                                    {{ fact.value }}
                                </dd>
                            </div>
                        </dl>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <LogisticsRecordDialog
            v-model:open="dialogOpen"
            kind="routes"
            :record="editing"
            :action="dialogAction()"
            @saved="onSaved"
        />
    </div>
</template>
