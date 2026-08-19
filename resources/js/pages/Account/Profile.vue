<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarCheck, Heart } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import SetPasswordCard from '@/components/organisms/SetPasswordCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';
import { getInitials } from '@/composables/useInitials';
import { useTranslations } from '@/composables/useTranslations';
import { formatTourDate } from '@/lib/format';

const { t } = useTranslations();

type NextBooking = {
    booking_number: string;
    tour: { name: string };
    starts_at: string;
};

const page = usePage();
const user = page.props.auth?.user;
const api = useApi();

const formData = ref({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
});
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const nextBooking = ref<NextBooking | null>(null);
const bookingsCount = ref(0);
const favoritesCount = ref(0);
const statsLoading = ref(true);

const initials = computed(() => getInitials(user?.name ?? ''));

async function loadStats() {
    statsLoading.value = true;

    try {
        const [bookingsRes, favoritesRes] = await Promise.all([
            fetch('/api/v1/account/bookings', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            }),
            fetch('/api/v1/account/favorites', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            }),
        ]);
        const bookingsJson = await bookingsRes.json();
        const favoritesJson = await favoritesRes.json();

        const upcoming = bookingsJson.data?.upcoming ?? [];
        const past = bookingsJson.data?.past ?? [];
        bookingsCount.value = upcoming.length + past.length;
        nextBooking.value = upcoming[0] ?? null;
        favoritesCount.value = (favoritesJson.data ?? []).length;
    } finally {
        statsLoading.value = false;
    }
}

function submit() {
    errors.value = {};
    processing.value = true;

    void api.put('/api/v1/account/profile', formData.value, {
        onSuccess: () => {
            toast.success(t('Perfil actualizado'));
            router.reload({ only: ['auth'] });
        },
        onError: (e) => {
            errors.value = e;
            toast.error(t('No pudimos actualizar el perfil'));
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

onMounted(loadStats);
</script>

<template>
    <Head :title="$t('Mi perfil')" />
    <div class="container mx-auto max-w-3xl space-y-8 px-4 py-8">
        <SetPasswordCard />

        <section class="rounded-xl border bg-card p-6">
            <div class="flex items-start gap-4">
                <Avatar class="size-16">
                    <AvatarImage
                        v-if="user?.avatar_url"
                        :src="user.avatar_url"
                        :alt="user.name"
                    />
                    <AvatarFallback class="text-lg font-semibold">
                        {{ initials }}
                    </AvatarFallback>
                </Avatar>
                <div class="flex-1 space-y-1">
                    <h1 class="text-2xl font-bold">{{ user?.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ user?.email }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <Link
                    href="/account/bookings"
                    class="rounded-lg border p-4 transition hover:border-primary hover:bg-muted/30"
                >
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <CalendarCheck class="size-4" />
                        {{ $t('Reservas') }}
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ statsLoading ? '—' : bookingsCount }}
                    </p>
                </Link>
                <Link
                    href="/account/favorites"
                    class="rounded-lg border p-4 transition hover:border-primary hover:bg-muted/30"
                >
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Heart class="size-4" />
                        {{ $t('Favoritos') }}
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ statsLoading ? '—' : favoritesCount }}
                    </p>
                </Link>
                <Link
                    v-if="nextBooking"
                    :href="`/bookings/${nextBooking.booking_number}`"
                    class="rounded-lg border p-4 transition hover:border-primary hover:bg-muted/30"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Próxima reserva') }}
                    </p>
                    <p class="mt-1 line-clamp-1 font-semibold">
                        {{ nextBooking.tour.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            formatTourDate(nextBooking.starts_at, {
                                withWeekday: false,
                            })
                        }}
                    </p>
                </Link>
                <div
                    v-else
                    class="rounded-lg border border-dashed p-4 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Sin próximas reservas') }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border bg-card p-6">
            <h2 class="text-lg font-semibold">{{ $t('Datos personales') }}</h2>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="name">{{ $t('Nombre') }}</Label>
                    <Input
                        id="name"
                        name="name"
                        v-model="formData.name"
                        required
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">
                        {{ errors.name }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="email">{{ $t('Email') }}</Label>
                    <Input
                        id="email"
                        name="email"
                        v-model="formData.email"
                        type="email"
                        required
                    />
                    <p v-if="errors.email" class="text-sm text-destructive">
                        {{ errors.email }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="phone">{{ $t('Teléfono') }}</Label>
                    <Input id="phone" name="phone" v-model="formData.phone" />
                    <p v-if="errors.phone" class="text-sm text-destructive">
                        {{ errors.phone }}
                    </p>
                </div>
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </form>
        </section>
    </div>
</template>
