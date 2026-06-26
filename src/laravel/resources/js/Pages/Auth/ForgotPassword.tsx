import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import TextInput from '@/components/TextInput';
import {
    authButtonClass,
    authErrorClass,
    authInputClass,
    authLabelClass,
} from '@/components/Auth/authStyles';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const STATUS_MESSAGES: Record<string, string> = {
    'We have emailed your password reset link.':
        'Мы отправили ссылку для сброса пароля на вашу почту.',
    'We have emailed your password reset link!':
        'Мы отправили ссылку для сброса пароля на вашу почту.',
};

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/forgot-password');
    };

    const statusMessage = status ? (STATUS_MESSAGES[status] ?? status) : undefined;

    return (
        <AuthSplitLayout
            title="Восстановление пароля"
            subtitle="Укажите email — мы отправим ссылку для создания нового пароля."
            backHref="/login"
            backLabel="Вернуться ко входу"
        >
            <Head title="Восстановление пароля" />

            {statusMessage && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {statusMessage}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Электронная почта" className={authLabelClass} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={authInputClass}
                        isFocused={true}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className={authErrorClass} />
                </div>

                <button type="submit" disabled={processing} className={authButtonClass}>
                    Отправить ссылку
                </button>
            </form>
        </AuthSplitLayout>
    );
}
