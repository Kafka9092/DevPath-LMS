import { authButtonClass, authLinkClass } from '@/components/Auth/authStyles';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

function MailIcon() {
    return (
        <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400">
            <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" strokeWidth={1.75} stroke="currentColor" aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
        </div>
    );
}

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/email/verification-notification');
    };

    return (
        <AuthSplitLayout title="Подтвердите почту">
            <Head title="Подтверждение почты" />

            <MailIcon />

            <p className="text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                Спасибо за регистрацию! Мы отправили письмо с ссылкой для подтвержения на ваш
                email. Откройте письмо и нажмите на ссылку — после этого можно будет войти в
                DevPath.
            </p>

            <p className="mt-3 text-sm text-slate-500 dark:text-gray-500">
                Не нашли письмо? Проверьте папку «Спам» или запросите новое.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-400">
                    Новая ссылка для подтверждения отправлена на ваш email.
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <button type="submit" disabled={processing} className={authButtonClass}>
                    Отправить письмо ещё раз
                </button>

                <div className="text-center">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className={`text-sm ${authLinkClass}`}
                    >
                        Выйти
                    </Link>
                </div>
            </form>
        </AuthSplitLayout>
    );
}
