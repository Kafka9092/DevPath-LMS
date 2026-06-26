import React from 'react';

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/).slice(0, 2);
    const letters = parts.map((p) => p[0]?.toUpperCase()).join('');
    return letters || 'U';
}

const sizeClasses = {
    sm: 'h-10 w-10 text-xs rounded-full',
    md: 'h-12 w-12 text-sm rounded-full',
    lg: 'h-24 w-24 text-2xl rounded-full',
    xl: 'h-12 w-12 text-base rounded-full',
    '2xl': 'h-14 w-14 text-lg rounded-full',
} as const;

interface UserAvatarProps {
    name: string;
    avatarUrl?: string | null;
    size?: keyof typeof sizeClasses;
    className?: string;
    ringed?: boolean;
}

export const UserAvatar: React.FC<UserAvatarProps> = ({
    name,
    avatarUrl,
    size = 'md',
    className = '',
    ringed = true,
}) => {
    const sizeClass = sizeClasses[size];
    const ringClass = ringed ? 'ring-2 ring-white dark:ring-gray-800' : '';

    if (avatarUrl) {
        return (
            <img
                src={avatarUrl}
                alt={name}
                className={`${sizeClass} object-cover shadow-sm ${ringClass} ${className}`}
            />
        );
    }

    return (
        <span
            className={`inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-fuchsia-500 font-bold text-white shadow-sm ${ringClass} ${sizeClass} ${className}`}
        >
            {initials(name)}
        </span>
    );
};
