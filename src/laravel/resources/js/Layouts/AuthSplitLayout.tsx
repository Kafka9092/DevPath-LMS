import { usePublicPageTheme } from '@/hooks/usePublicPageTheme';
import { AuthSessionSync } from '@/components/AuthSessionSync';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

interface AuthSplitLayoutProps extends PropsWithChildren {
    title: string;
    subtitle?: ReactNode;
    activeTab?: 'login' | 'register';
    backHref?: string;
    backLabel?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    subtitle,
    activeTab,
    backHref,
    backLabel = 'Назад',
}: AuthSplitLayoutProps) {
    usePublicPageTheme();

    return (
        <div className="flex min-h-screen bg-white text-slate-900 dark:bg-gray-950 dark:text-gray-100">
            <AuthSessionSync />
            <div className="flex w-full flex-col justify-center bg-white px-6 py-16 dark:bg-gray-950 sm:px-12 lg:w-[44%] lg:px-16 xl:px-24">
                <div className="mx-auto w-full max-w-md">
                    {backHref && (
                        <Link
                            href={backHref}
                            className="mb-8 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-violet-700"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            {backLabel}
                        </Link>
                    )}

                    {activeTab && (
                        <div className="mb-10 flex border-b border-slate-200 dark:border-gray-800">
                            <Link
                                href="/login"
                                className={`pb-3 pr-8 text-sm font-medium transition-colors border-b-2 -mb-px ${
                                    activeTab === 'login'
                                        ? 'border-violet-600 text-violet-700 dark:text-violet-400'
                                        : 'border-transparent text-slate-500 dark:text-gray-400 hover:text-slate-800 dark:hover:text-gray-200'
                                }`}
                            >
                                Вход
                            </Link>
                            <Link
                                href="/register"
                                className={`pb-3 pr-8 text-sm font-medium transition-colors border-b-2 -mb-px ${
                                    activeTab === 'register'
                                        ? 'border-violet-600 text-violet-700 dark:text-violet-400'
                                        : 'border-transparent text-slate-500 dark:text-gray-400 hover:text-slate-800 dark:hover:text-gray-200'
                                }`}
                            >
                                Регистрация
                            </Link>
                        </div>
                    )}

                    <h1 className="text-[28px] font-bold tracking-tight text-slate-900 dark:text-gray-100 leading-tight">
                        {title}
                    </h1>

                    {subtitle && (
                        <p className="mt-2 text-sm text-slate-600 dark:text-gray-400">{subtitle}</p>
                    )}

                    <div className="mt-8">{children}</div>
                </div>
            </div>

            <div
                className="relative hidden lg:block lg:w-[56%]"
                aria-hidden="true"
                style={{
                    backgroundImage: 'url(/images/auth-bg.jpg)',
                    backgroundSize: 'cover',
                    backgroundRepeat: 'no-repeat',
                    backgroundPosition: 'right center',
                }}
            />
        </div>
    );
}
