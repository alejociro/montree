export type TenantRole = 'admin' | 'operator' | 'guide' | 'customer';

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

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
