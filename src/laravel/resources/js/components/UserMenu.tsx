import Dropdown from '@/components/Dropdown';
import { ThemeSwitcher } from '@/components/ThemeSwitcher';
import { UserAvatar } from '@/components/UserAvatar';
import {
    ArrowRightOnRectangleIcon,
    ChevronDownIcon,
    Cog6ToothIcon,
} from '@heroicons/react/24/outline';
import { Link, usePage } from '@inertiajs/react';
import React, { ReactNode } from 'react';
import { User } from '@/types';

const menuItemClass =
    'group flex w-full items-center gap-3.5 rounded-lg px-3.5 py-3 text-base font-medium text-slate-700 transition duration-150 ease-in-out hover:bg-violet-50 hover:text-violet-700 focus:bg-violet-50 focus:text-violet-700 focus:outline-none dark:text-gray-200 dark:hover:bg-white/5 dark:hover:text-violet-300 dark:focus:bg-white/5 [&_svg]:h-6 [&_svg]:w-6 [&_svg]:shrink-0 [&_svg]:text-slate-400 group-hover:[&_svg]:text-violet-600 dark:[&_svg]:text-gray-400 dark:group-hover:[&_svg]:text-violet-400';

function MenuItem({
    href,
    method,
    as,
    onClick,
    icon,
    children,
    destructive = false,
}: {
    href: string;
    method?: 'post' | 'get' | 'put' | 'patch' | 'delete';
    as?: 'button' | 'a';
    onClick?: (e: React.MouseEvent) => void;
    icon: ReactNode;
    children: ReactNode;
    destructive?: boolean;
}) {
    const className = `${menuItemClass} ${
        destructive
            ? 'text-red-600 hover:bg-red-50 hover:text-red-700 focus:bg-red-50 focus:text-red-700 group-hover:[&_svg]:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:text-red-300 dark:group-hover:[&_svg]:text-red-400'
            : ''
    }`;

    return (
        <Dropdown.Link
            href={href}
            method={method}
            as={as}
            onClick={onClick}
            className={className}
        >
            {icon}
            {children}
        </Dropdown.Link>
    );
}

export const UserMenu: React.FC = () => {
    const user = usePage().props.auth?.user as User | null | undefined;

    if (!user) {
        return (
            <Link
                href="/login"
                className="inline-flex items-center rounded-xl bg-violet-600 px-5 py-2.5 text-base font-semibold text-white shadow-md shadow-violet-200 transition hover:bg-orange-500 active:scale-95 dark:shadow-violet-900/30"
            >
                Войти
            </Link>
        );
    }

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="inline-flex items-center gap-3 bg-transparent p-0 transition hover:opacity-85 focus:outline-none"
                >
                    <UserAvatar
                        name={user.name}
                        avatarUrl={user.avatar_url}
                        size="2xl"
                        ringed={false}
                    />
                    <span className="max-w-[160px] truncate text-base font-semibold text-slate-900 dark:text-gray-100">
                        {user.name}
                    </span>
                    <ChevronDownIcon className="h-5 w-5 shrink-0 text-slate-400 dark:text-gray-500" />
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content
                align="right"
                width="80"
                contentClasses="overflow-hidden rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xl dark:border-gray-700 dark:bg-gray-900"
            >
                <div className="rounded-xl bg-gradient-to-br from-violet-50 to-fuchsia-50 px-4 py-4 dark:from-violet-950/50 dark:to-fuchsia-950/30">
                    <div className="flex items-center gap-4">
                        <UserAvatar
                            name={user.name}
                            avatarUrl={user.avatar_url}
                            size="lg"
                        />
                        <div className="min-w-0">
                            <div className="truncate text-base font-semibold text-slate-900 dark:text-gray-100">
                                {user.name}
                            </div>
                            {user.email && (
                                <div className="truncate text-sm text-slate-500 dark:text-gray-400">
                                    {user.email}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="my-2.5 h-px bg-slate-100 dark:bg-gray-800" />

                <div className="space-y-0.5">
                    <MenuItem
                        href="/profile"
                        icon={<Cog6ToothIcon />}
                    >
                        Настройки профиля
                    </MenuItem>
                </div>

                <div className="my-2.5 h-px bg-slate-100 dark:bg-gray-800" />

                <div className="px-1.5 py-1">
                    <ThemeSwitcher />
                </div>

                <div className="my-2.5 h-px bg-slate-100 dark:bg-gray-800" />

                <MenuItem
                    href="/logout"
                    method="post"
                    as="button"
                    destructive
                    icon={<ArrowRightOnRectangleIcon />}
                >
                    Выйти
                </MenuItem>
            </Dropdown.Content>
        </Dropdown>
    );
};
