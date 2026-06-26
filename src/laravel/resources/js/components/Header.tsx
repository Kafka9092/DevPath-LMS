import React, { ReactNode } from 'react';

interface HeaderProps {
    title: string;
    buttonText: string;
    onButtonClick: () => void;
    rightSlot?: ReactNode;
}

const PlusIcon = () => (
    <svg
        className="h-5 w-5"
        fill="none"
        viewBox="0 0 24 24"
        strokeWidth={2.5}
        stroke="currentColor"
    >
        <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M12 4.5v15m7.5-7.5h-15"
        />
    </svg>
);


export const Header: React.FC<HeaderProps> = ({
    title,
    buttonText,
    onButtonClick,
    rightSlot,
}) => {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 px-8 pt-4 pb-6 mb-8">
            <h1 className="min-w-0 truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-gray-100 md:text-3xl">
                {title}
            </h1>

            <div className="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    onClick={onButtonClick}
                    className="flex items-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-base font-semibold text-white shadow-md shadow-violet-200 transition-all duration-150 hover:bg-orange-500 active:scale-95 dark:shadow-violet-900/30"
                >
                    <PlusIcon />
                    {buttonText}
                </button>
                {rightSlot}
            </div>
        </div>
    );
};
