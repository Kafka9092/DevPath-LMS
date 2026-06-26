import { syncAuthUserId } from '@/lib/authSession';
import { usePage } from '@inertiajs/react';

type AuthPageProps = {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
};

export function AuthSessionSync() {
    const { auth } = usePage<AuthPageProps>().props;

    syncAuthUserId(auth?.user?.id ?? null);

    return null;
}
