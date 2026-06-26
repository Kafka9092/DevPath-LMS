import SocialAuthButtons from '@/components/Auth/SocialAuthButtons';
import TurnstileField from '@/components/Auth/TurnstileField';
import {
    authButtonClass,
    authErrorClass,
    authInputClass,
    authLabelClass,
} from '@/components/Auth/authStyles';
import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import TextInput from '@/components/TextInput';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Register({
    turnstileSiteKey,
}: {
    turnstileSiteKey: string;
}) {
    const [turnstileKey, setTurnstileKey] = useState(0);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        captcha_token: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/register', {
            onFinish: () => reset('password', 'password_confirmation', 'captcha_token'),
            onError: () => {
                setData('captcha_token', '');
                setTurnstileKey((key) => key + 1);
            },
        });
    };

    return (
        <AuthSplitLayout title="Создание аккаунта" activeTab="register">
            <Head title="Регистрация" />

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="name" value="Имя" className={authLabelClass} />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className={authInputClass}
                        autoComplete="name"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Электронная почта" className={authLabelClass} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={authInputClass}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Пароль" className={authLabelClass} />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={authInputClass}
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Подтверждение пароля" className={authLabelClass} />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className={authInputClass}
                        autoComplete="off"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} className={authErrorClass} />
                </div>

                <TurnstileField
                    key={turnstileKey}
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
                    Зарегистрироваться
                </button>
            </form>

            <SocialAuthButtons />
        </AuthSplitLayout>
    );
}
