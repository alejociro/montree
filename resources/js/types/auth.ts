export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_path: string | null;
    avatar_url: string | null;
    phone: string | null;
    email_verified_at: string | null;
    /**
     * Permisos efectivos del usuario en el tenant actual (`modulo.accion`).
     * Union de todos sus roles activos; para `super_admin`, el catalogo completo.
     * Solo para UI — la autorizacion real vive en el backend (F018, contracts.md §2).
     */
    permissions: string[];
    isSuperAdmin: boolean;
    mustSetPassword: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: string[];
};

/**
 * Chequeo de permisos para la UI. Con un array la semantica es "alguno de" (OR),
 * que es lo que necesita el menu: un item se muestra si el usuario puede abrir
 * al menos una de las acciones que representa.
 */
export type PermissionCheck = (permission: string | string[]) => boolean;

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
