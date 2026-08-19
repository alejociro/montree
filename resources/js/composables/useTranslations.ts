import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { update as updateLocale } from '@/routes/locale';
import type {
    LocaleOption,
    PluralTranslator,
    TranslationReplacements,
    Translator,
} from '@/types/locale';

export type UseTranslationsReturn = {
    t: Translator;
    tChoice: PluralTranslator;
    locale: ComputedRef<string>;
    locales: ComputedRef<LocaleOption[]>;
    setLocale: (code: string) => void;
};

function interpolate(
    line: string,
    replacements: TranslationReplacements,
): string {
    return Object.entries(replacements).reduce(
        (carry, [key, value]) =>
            carry
                .replace(new RegExp(`:${key}\\b`, 'g'), String(value))
                .replace(new RegExp(`\\{${key}\\}`, 'g'), String(value)),
        line,
    );
}

/**
 * Elige la forma plural con la misma sintaxis de `trans_choice`:
 * `{0} nada|{1} uno|[2,*] :count cosas`, o el atajo `singular|plural`.
 */
function choose(message: string, count: number): string {
    const segments = message.split('|');

    if (segments.length === 1) {
        return message;
    }

    for (const segment of segments) {
        const exact = segment.match(/^\s*\{\s*(-?\d+)\s*\}(.*)/s);

        if (exact && Number(exact[1]) === count) {
            return exact[2].trim();
        }

        const range = segment.match(
            /^\s*\[\s*(-?\d+)\s*,\s*(\d+|\*)\s*\](.*)/s,
        );

        if (!range) {
            continue;
        }

        const from = Number(range[1]);
        const to =
            range[2] === '*' ? Number.POSITIVE_INFINITY : Number(range[2]);

        if (count >= from && count <= to) {
            return range[3].trim();
        }
    }

    // Atajo `singular|plural`: sin rangos declarados, el ingles y el espanol comparten
    // la misma regla — todo lo que no es exactamente 1 va al plural.
    const plain = segments.filter((segment) => !/^\s*[{[]/.test(segment));

    return (count === 1 ? plain[0] : plain[1]) ?? segments[0];
}

/**
 * Traduce fuera de un componente. `usePage()` es un singleton reactivo del router, no
 * un `inject`, asi que se puede leer desde un helper suelto (`lib/format.ts`) o desde
 * el registro global de `app.ts`. Se resuelve en cada llamada, no al cargar el modulo.
 */
export const translate: Translator = (key, replacements) =>
    useTranslations().t(key, replacements);

export const translateChoice: PluralTranslator = (key, count, replacements) =>
    useTranslations().tChoice(key, count, replacements);

/**
 * Traducciones de la aplicacion. La fuente de verdad es la prop compartida
 * `translations` que arma `HandleInertiaRequests` (contracts.md §Props compartidas):
 * no hay estado local ni cache en el cliente, asi que el idioma solo cambia cuando el
 * backend responde y frontend y backend nunca pueden discrepar.
 *
 * Una clave sin entrada devuelve la clave, que ES el texto en espanol (plan.md §5):
 * un hueco de traduccion degrada a espanol legible, no a una clave cruda en pantalla.
 */
export function useTranslations(): UseTranslationsReturn {
    const page = usePage();

    const messages = computed<Record<string, string>>(
        () => page.props.translations ?? {},
    );

    const t: Translator = (key, replacements = {}) =>
        interpolate(messages.value[key] ?? key, replacements);

    function tChoice(
        key: string,
        count: number,
        replacements: TranslationReplacements = {},
    ): string {
        const message = choose(messages.value[key] ?? key, count);

        return interpolate(message, { count, ...replacements });
    }

    function setLocale(code: string): void {
        // WHY: `preserveState: false` es obligatorio, no cosmetico. Con el estado
        // preservado Inertia reusa la instancia de la pagina y todo lo que se calculo
        // una vez en el setup (mapas de etiquetas, textos derivados) se queda en el
        // idioma anterior. Forzando el remount, cada componente vuelve a leer `t()`.
        router.patch(
            updateLocale().url,
            { locale: code },
            { preserveScroll: true, preserveState: false },
        );
    }

    return {
        t,
        tChoice,
        locale: computed(() => page.props.locale ?? 'es'),
        locales: computed(() => page.props.locales ?? []),
        setLocale,
    };
}
