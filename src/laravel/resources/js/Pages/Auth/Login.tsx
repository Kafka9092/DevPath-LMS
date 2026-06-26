import SocialAuthButtons from '@/components/Auth/SocialAuthButtons';
import TurnstileField from '@/components/Auth/TurnstileField';
import {
    authButtonClass,
    authErrorClass,
    authInputClass,
    authLabelClass,
    authLinkClass,
} from '@/components/Auth/authStyles';
import Checkbox from '@/components/Checkbox';
import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import TextInput from '@/components/TextInput';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
    turnstileSiteKey,
}: {
    status?: string;
    canResetPassword: boolean;
    turnstileSiteKey: string;
}) {
    const pageErrors = (usePage().props.errors ?? {}) as Record<string, string | string[]>;
    const oauthErrorRaw = pageErrors.error;
    const oauthError = Array.isArray(oauthErrorRaw) ? oauthErrorRaw[0] : oauthErrorRaw;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
        captcha_token: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password', 'captcha_token'),
        });
    };

    return (
        <AuthSplitLayout title="Вход в аккаунт" activeTab="login">
            <Head title="Вход" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
                    {status}
                </div>
            )}

            {oauthError && (
                <div className="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
                    {oauthError}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel
                        htmlFor="email"
                        value="Электронная почта"
                        className={authLabelClass}
                    />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={authInputClass}
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password"
                        value="Пароль"
                        className={authLabelClass}
                    />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={authInputClass}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className={authErrorClass} />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            className="rounded border-slate-300 bg-white text-violet-600 focus:ring-violet-500 focus:ring-offset-white dark:border-gray-600 dark:bg-gray-900 dark:focus:ring-offset-gray-950"
                            onChange={(e) =>
                                setData('remember', (e.target.checked || false) as false)
                            }
                        />
                        <span className="text-sm text-slate-600 dark:text-gray-400">Запомнить меня</span>
                    </label>

                    {canResetPassword && (
                        <Link href="/forgot-password" className={`text-sm ${authLinkClass}`}>
                            Забыли пароль?
                        </Link>
                    )}
                </div>

                <TurnstileField
                    siteKey={turnstileSiteKey}
                    onSuccess={(token) => setData('captcha_token', token)}
                    onExpire={() => setData('captcha_token', '')}
                    error={errors.captcha_token}
                />

                <button
                    type="submit"
                    disabled={processing || (turnstileSiteKey !== '' && data.captcha_token === '')}
                    className={authButtonClass}
                >
                    Войти
                </button>
            </form>

            <SocialAuthButtons />
        </AuthSplitLayout>
    );
}
