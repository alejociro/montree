import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import SubdomainAvailabilityController from '@/actions/App/Http/Controllers/Api/V1/Onboarding/SubdomainAvailabilityController';
import type {
    SubdomainAvailability,
    SubdomainAvailabilityReason,
    SubdomainStatus,
} from '@/types/onboarding.types';

const SLUG_PATTERN = /^[a-z0-9][a-z0-9-]{1,62}$/;

type UseSubdomainAvailabilityOptions = {
    debounce?: number;
};

type UseSubdomainAvailabilityReturn = {
    status: Ref<SubdomainStatus>;
    reason: Ref<SubdomainAvailabilityReason>;
};

export function useSubdomainAvailability(
    source: Ref<string>,
    options: UseSubdomainAvailabilityOptions = {},
): UseSubdomainAvailabilityReturn {
    const status = ref<SubdomainStatus>('idle');
    const reason = ref<SubdomainAvailabilityReason>(null);

    const debounce = options.debounce ?? 400;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let requestId = 0;

    async function check(slug: string): Promise<void> {
        const current = ++requestId;

        try {
            const response = await fetch(
                SubdomainAvailabilityController.url({ query: { slug } }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (current !== requestId) {
                return;
            }

            if (!response.ok) {
                status.value = 'idle';
                reason.value = null;

                return;
            }

            const data = (await response.json()) as SubdomainAvailability;

            if (current !== requestId) {
                return;
            }

            status.value = data.available ? 'available' : 'unavailable';
            reason.value = data.reason;
        } catch {
            if (current !== requestId) {
                return;
            }

            status.value = 'idle';
            reason.value = null;
        }
    }

    watch(source, (value) => {
        const slug = value.trim().toLowerCase();

        if (timer) {
            clearTimeout(timer);
        }

        requestId += 1;

        if (slug === '') {
            status.value = 'idle';
            reason.value = null;

            return;
        }

        if (!SLUG_PATTERN.test(slug)) {
            status.value = 'unavailable';
            reason.value = 'invalid_format';

            return;
        }

        status.value = 'checking';
        reason.value = null;
        timer = setTimeout(() => check(slug), debounce);
    });

    return { status, reason };
}
