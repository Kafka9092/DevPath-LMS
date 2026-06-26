import DangerButton from '@/components/DangerButton';
import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import Modal from '@/components/Modal';
import SecondaryButton from '@/components/SecondaryButton';
import TextInput from '@/components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { profileInputClass, profileLabelClass, profileSectionDescClass, profileSectionTitleClass } from '../profileStyles';

export default function DeleteUserForm({
    className = '',
}: {
    className?: string;
}) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy('/profile', {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className={profileSectionTitleClass}>
                    Удаление аккаунта
                </h2>

                <p className={profileSectionDescClass}>
                    После удаления все данные будут безвозвратно удалены.
                    Сохраните нужную информацию заранее.
                </p>
            </header>

            <DangerButton
                onClick={confirmUserDeletion}
                className="!rounded-xl !px-5 !py-2.5 !text-sm !font-semibold !normal-case !tracking-normal"
            >
                Удалить аккаунт
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-gray-100">
                        Удалить аккаунт?
                    </h2>

                    <p className="mt-2 text-sm text-slate-500 dark:text-gray-400">
                        Это действие необратимо. Введите пароль для
                        подтверждения удаления аккаунта.
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Пароль"
                            className={profileLabelClass}
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className={profileInputClass}
                            isFocused
                            placeholder="Введите пароль"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2 text-red-500"
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            onClick={closeModal}
                            className="!rounded-xl !normal-case !tracking-normal"
                        >
                            Отмена
                        </SecondaryButton>

                        <DangerButton
                            className="!rounded-xl !normal-case !tracking-normal"
                            disabled={processing}
                        >
                            Удалить аккаунт
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
