import { useTranslations } from '@/composables/useTranslations';

/**
 * Páginas legales de la plataforma (host de Montree). Las comparten el footer
 * de la landing y el de PlatformShell para que no se desalineen.
 */
export function useLegalLinks() {
    const { t } = useTranslations();

    return [
        { label: t('Términos y condiciones'), href: '/terminos-y-condiciones' },
        { label: t('Política de privacidad'), href: '/politica-de-privacidad' },
        { label: t('Política de cookies'), href: '/politica-de-cookies' },
        { label: t('Política de pago'), href: '/politica-de-pago' },
    ];
}
