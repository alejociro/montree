import type { CategoryIcon } from '@/types/enums.generated';

export type { CategoryIcon };

/** Fila del panel de categorías (`App\Http\Resources\Admin\CategoryResource`). */
export type AdminCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: CategoryIcon | null;
    image_url: string | null;
    display_order: number;
    is_active: boolean;
    tours_count: number;
};

/** Opción del selector visual de íconos, rotulada por el backend. */
export type CategoryIconOption = {
    value: CategoryIcon;
    label: string;
};
