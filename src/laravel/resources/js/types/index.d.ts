export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar_url?: string | null;
    roles?: string[];
    is_admin?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    features?: {
        adaptiveTestContinueBar?: boolean;
    };
};
