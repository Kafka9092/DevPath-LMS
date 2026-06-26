import { DevPathLogo } from '@/components/DevPathLogo';
import { UserMenu } from '@/components/UserMenu';
import { Squares2X2Icon } from '@heroicons/react/24/outline';
import { usePage } from '@inertiajs/react';
import { User } from '@/types';

export function AppTopBar() {
    const user = usePage().props.auth?.user as User | null | undefined;
    const isAdmin = user?.is_admin === true;

    return (
        <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-md surface-header dark:border-gray-800 dark:bg-gray-950/95">
            <div className="flex min-h-[88px] items-center justify-between gap-6 px-8 pb-4 pt-5">
                <DevPathLogo />

                <div className="flex items-center gap-4">
                    {isAdmin && (
                        <a
                            href="/admin/"
                            className="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 no-underline transition cursor-pointer hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300 dark:hover:border-orange-700 dark:hover:bg-orange-950/30 dark:hover:text-orange-300"
                        >
                            <Squares2X2Icon className="h-5 w-5 shrink-0" />
                            <span className="hidden sm:inline">Админка</span>
                        </a>
                    )}
                    <UserMenu />
                </div>
            </div>
        </header>
    );
}
