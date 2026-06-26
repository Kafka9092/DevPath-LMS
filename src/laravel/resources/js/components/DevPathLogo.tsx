import { Link } from '@inertiajs/react';

interface DevPathLogoProps {
    href?: string;
    className?: string;
}

export function DevPathLogo({ href = '/main', className = '' }: DevPathLogoProps) {
    const inner = (
        <span
            className={`inline-flex items-center gap-1.5 select-none ${className}`}
        >
            <span className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-gray-100">
                Dev
            </span>
            <span className="text-2xl font-extrabold tracking-tight text-violet-700 dark:text-violet-400">
                Path
            </span>
        </span>
    );

    if (href) {
        return (
            <Link href={href} className="transition opacity-90 hover:opacity-100">
                {inner}
            </Link>
        );
    }

    return inner;
}
