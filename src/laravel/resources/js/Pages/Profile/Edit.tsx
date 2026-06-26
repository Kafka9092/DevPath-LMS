import { AppLayout } from '@/components/Sidebar';
import { Breadcrumbs } from '@/components/Breadcrumbs';
import { PageHeader } from '@/components/PageHeader';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { profileCardClass } from './profileStyles';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const breadcrumbs = [
        { label: 'DevPath', path: '/main' },
        { label: 'Профиль', path: '/profile' },
    ];

    return (
        <AppLayout>
            <Head title="Профиль" />

            <Breadcrumbs crumbs={breadcrumbs} />

            <PageHeader title="Настройки профиля" />

            <div className="mx-auto max-w-3xl space-y-6 px-8 pb-12 pt-2">
                <div className={profileCardClass}>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </div>

                <div className={profileCardClass}>
                    <UpdatePasswordForm />
                </div>

                <div
                    className={`${profileCardClass} border-red-100 dark:border-red-900/40`}
                >
                    <DeleteUserForm />
                </div>
            </div>
        </AppLayout>
    );
}
