import type { TenantPlan } from '@/types';

const PLAN_LABELS: Record<TenantPlan, string> = {
    basic: 'Basic',
    professional: 'Professional',
    enterprise: 'Enterprise',
};

export function planLabel(plan: TenantPlan): string {
    return PLAN_LABELS[plan] ?? plan;
}
