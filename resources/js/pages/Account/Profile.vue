<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarCheck, Heart } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';
import { getInitials } from '@/composables/useInitials';
import { formatTourDate } from '@/lib/format';

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
        onSuccess: () => toast.success('Perfil actualizado'),
        onError: (e) => {
            errors.value = e;
            toast.error('No pudimos actualizar el perfil');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

onMounted(loadStats);
</script>

<template>
    <Head title="Mi perfil" />
    <div class="container mx-auto max-w-3xl space-y-8 px-4 py-8">
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
                        Reservas
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
                        Favoritos
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
                    <p class="text-sm text-muted-foreground">Próxima reserva</p>
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
                        Sin próximas reservas
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border bg-card p-6">
            <h2 class="text-lg font-semibold">Datos personales</h2>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="formData.name" required />
                    <p v-if="errors.name" class="text-sm text-destructive">
                        {{ errors.name }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="formData.email"
                        type="email"
                        required
                    />
                    <p v-if="errors.email" class="text-sm text-destructive">
                        {{ errors.email }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="phone">Teléfono</Label>
                    <Input id="phone" v-model="formData.phone" />
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
