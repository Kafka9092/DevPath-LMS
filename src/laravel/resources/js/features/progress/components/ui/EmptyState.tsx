import React from "react";
import { Link } from "@inertiajs/react";

export function EmptyState({
    icon,
    title,
    description,
    actionLabel,
    actionHref,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
    actionLabel: string;
    actionHref: string;
}) {
    return (
        <div className="rounded-2xl surface-elevated">
        <div className="rounded-2xl border border-dashed border-slate-200 dark:border-gray-700 bg-slate-50/80 dark:bg-gray-900/50 px-8 py-14 text-center overflow-hidden">
            <div className="mx-auto w-14 h-14 rounded-2xl bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 flex items-center justify-center text-slate-400 mb-5 shadow-sm">
                {icon}
            </div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-gray-100">{title}</h3>
            <p className="text-sm text-slate-500 dark:text-gray-400 mt-2 max-w-md mx-auto">{description}</p>
            <Link
                href={actionHref}
                className="inline-flex mt-6 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
                style={{ background: "var(--empty-cta, #2563eb)" }}
            >
                {actionLabel}
            </Link>
        </div>
        </div>
    );
}
