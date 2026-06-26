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

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthSplitLayout
            title="Новый пароль"
            subtitle="Придумайте новый пароль для вашего аккаунта."
            backHref="/login"
            backLabel="Вернуться ко входу"
        >
            <Head title="Сброс пароля" />

            <form onSubmit={submit} className="space-y-5">
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
                    />

                    <InputError message={errors.email} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Новый пароль" className={authLabelClass} />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={authInputClass}
                        autoComplete="new-password"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className={authErrorClass} />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Подтверждение пароля"
                        className={authLabelClass}
                    />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className={authInputClass}
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className={authErrorClass}
                    />
                </div>

                <button type="submit" disabled={processing} className={authButtonClass}>
                    Сохранить пароль
                </button>
            </form>
        </AuthSplitLayout>
    );
}
