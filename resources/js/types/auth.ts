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
    tenantRole: TenantRole | null;
    isSuperAdmin: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
