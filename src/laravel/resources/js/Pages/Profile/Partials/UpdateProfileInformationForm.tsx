import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import PrimaryButton from '@/components/PrimaryButton';
import TextInput from '@/components/TextInput';
import { UserAvatar } from '@/components/UserAvatar';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    ChangeEvent,
    FormEventHandler,
    useEffect,
    useRef,
    useState,
} from 'react';
import {
    profileButtonClass,
    profileInputClass,
    profileLabelClass,
    profileLinkClass,
    profileSectionDescClass,
    profileSectionTitleClass,
} from '../profileStyles';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(
        user.avatar_url ?? null,
    );

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            avatar: null as File | null,
            remove_avatar: false as boolean,
        });

    useEffect(() => {
        setPreviewUrl(user.avatar_url ?? null);
    }, [user.avatar_url]);

    useEffect(() => {
        return () => {
            if (previewUrl?.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        if (previewUrl?.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
        }

        setData('avatar', file);
        setData('remove_avatar', false);
        setPreviewUrl(URL.createObjectURL(file));
    };

    const handleRemoveAvatar = () => {
        if (previewUrl?.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
        }

        setData('avatar', null);
        setData('remove_avatar', true);
        setPreviewUrl(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch('/profile', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData('avatar', null);
                setData('remove_avatar', false);

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className={profileSectionTitleClass}>
                    Информация профиля
                </h2>

                <p className={profileSectionDescClass}>
                    Обновите фото, имя и email вашего аккаунта.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel
                        value="Фото профиля"
                        className={profileLabelClass}
                    />

                    <div className="mt-3 flex flex-wrap items-center gap-5">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={data.name}
                                className="h-24 w-24 rounded-2xl object-cover shadow-md ring-2 ring-violet-100"
                            />
                        ) : (
                            <UserAvatar
                                name={data.name}
                                size="lg"
                                className="ring-2 ring-violet-100"
                            />
                        )}

                        <div className="flex flex-col gap-2">
                            <input
                                ref={fileInputRef}
                                id="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                className="hidden"
                                onChange={handleFileChange}
                            />

                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="inline-flex items-center justify-center rounded-xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-900/30 dark:text-violet-300 dark:hover:bg-violet-900/50"
                            >
                                {previewUrl
                                    ? 'Сменить фото'
                                    : 'Загрузить фото'}
                            </button>

                            {previewUrl && (
                                <button
                                    type="button"
                                    onClick={handleRemoveAvatar}
                                    className="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                >
                                    Удалить фото
                                </button>
                            )}

                            <p className="text-xs text-slate-400 dark:text-gray-500">
                                JPG, PNG, WEBP или GIF до 2 МБ
                            </p>
                        </div>
                    </div>

                    <InputError
                        className="mt-2 text-red-500"
                        message={errors.avatar}
                    />
                </div>

                <div>
                    <InputLabel
                        htmlFor="name"
                        value="Имя"
                        className={profileLabelClass}
                    />

                    <TextInput
                        id="name"
                        className={profileInputClass}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoComplete="name"
                    />

                    <InputError
                        className="mt-2 text-red-500"
                        message={errors.name}
                    />
                </div>

                <div>
                    <InputLabel
                        htmlFor="email"
                        value="Email"
                        className={profileLabelClass}
                    />

                    <TextInput
                        id="email"
                        type="email"
                        className={profileInputClass}
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError
                        className="mt-2 text-red-500"
                        message={errors.email}
                    />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-slate-700 dark:text-gray-300">
                            Ваш email не подтверждён.{' '}
                            <Link
                                href="/email/verification-notification"
                                method="post"
                                as="button"
                                className={profileLinkClass}
                            >
                                Отправить письмо повторно
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-emerald-600">
                                Новая ссылка для подтверждения отправлена на ваш
                                email.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton
                        disabled={processing}
                        className={profileButtonClass}
                    >
                        Сохранить
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-emerald-600">Сохранено.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
