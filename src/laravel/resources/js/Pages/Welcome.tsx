import { Head, Link } from '@inertiajs/react';
import { usePublicPageTheme } from '@/hooks/usePublicPageTheme';

export default function Welcome() {
    usePublicPageTheme();

    return (
        <>
            <Head title="DevPath" />

            <main className="flex min-h-screen flex-col items-center justify-center bg-white px-4 dark:bg-gray-950">
                <div className="flex max-w-3xl flex-col items-center gap-4 text-center">
                    <h1 className="text-5xl font-extrabold tracking-tight sm:text-6xl md:text-7xl">
                        <span className="text-slate-950 dark:text-gray-100">Dev</span>
                        <span className="text-violet-700 dark:text-violet-400">Path</span>
                    </h1>

                    <p className="text-base text-slate-600 dark:text-gray-400 md:text-lg">
                        Персонализированное обучение
                        <br />
                        программированию с ИИ
                    </p>

                    <Link
                        href="/login"
                        className="mt-2 inline-flex min-w-[220px] items-center justify-center rounded-2xl bg-violet-700 px-12 py-3.5 text-sm font-bold tracking-wide text-white shadow-lg shadow-violet-300/40 transition hover:bg-orange-500 active:scale-[0.98] cursor-pointer"
                    >
                        Начать обучение
                    </Link>
                </div>
            </main>
        </>
    );
}
