/**
 * Asigna un valor nuevo a un formulario de Inertia y limpia los errores de los
 * campos que cambiaron.
 *
 * WHY: `useForm` conserva los errores del último envío hasta el siguiente, así
 * que «El nombre es obligatorio» seguía en rojo debajo del campo aunque el
 * usuario ya lo hubiera diligenciado. El error se retira en cuanto el campo se
 * toca —la validación real la sigue haciendo el backend al guardar—, y arrastra
 * también los errores anidados (`itinerary.0.title` cae con `itinerary`).
 */
type ErrorBag = Record<string, string | undefined>;

/**
 * `InertiaForm<T>` tipa `clearErrors` con la unión literal de sus campos —y la
 * lista se calcula en tiempo de ejecución—, así que acá se pide solo la bolsa
 * de errores y la llamada se estrecha en el punto de uso.
 */
type ClearableForm = {
    errors: ErrorBag;
};

export function changedKeys<T extends Record<string, unknown>>(
    current: Partial<T>,
    next: T,
): string[] {
    return Object.keys(next).filter(
        (key) => !Object.is(current[key as keyof T], next[key as keyof T]),
    );
}

export function staleErrorFields(
    errors: ErrorBag,
    changed: string[],
): string[] {
    return Object.keys(errors).filter(
        (field) =>
            errors[field] !== undefined &&
            changed.some((key) => field === key || field.startsWith(`${key}.`)),
    );
}

export function applyFormValue<T extends Record<string, unknown>>(
    form: ClearableForm,
    next: T,
): void {
    const changed = changedKeys(form as unknown as Partial<T>, next);

    Object.assign(form, next);

    const stale = staleErrorFields(form.errors, changed);

    if (stale.length > 0) {
        (
            (form as unknown as Record<string, unknown>).clearErrors as (
                ...fields: string[]
            ) => unknown
        )(...stale);
    }
}
