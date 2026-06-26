import React from "react";

export function PanelCard({
    title,
    subtitle,
    accent,
    children,
    className = "",
}: {
    title: string;
    subtitle?: string;
    accent?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={`rounded-2xl surface-elevated ${className}`}>
            <div className="rounded-2xl border border-slate-200/80 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div
                className="px-6 py-4 border-b border-slate-100 dark:border-gray-800"
                style={accent ? { backgroundColor: `${accent}0d` } : undefined}
            >
                <h3 className="text-sm font-semibold text-slate-800 dark:text-gray-100">{title}</h3>
                {subtitle && <p className="text-xs text-slate-500 mt-0.5">{subtitle}</p>}
            </div>
            <div className="p-6">{children}</div>
            </div>
        </section>
    );
}
