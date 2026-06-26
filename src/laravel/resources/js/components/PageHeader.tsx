import { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
}

export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 px-8 pb-2 pt-6">
            <div className="min-w-0">
                <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-gray-100 md:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-slate-500 dark:text-gray-400 md:text-base">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-3">{actions}</div>
            )}
        </div>
    );
}
