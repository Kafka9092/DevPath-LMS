import React from "react";

export function StatCard({
    label,
    value,
    hint,
    accent = "#2563eb",
}: {
    label: string;
    value: string | number;
    hint?: string;
    accent?: string;
}) {
    return (
        <div
            className="
                group rounded-2xl surface-elevated relative
                transition-all duration-200 ease-out
                hover:-translate-y-0.5
            "
        >
            <div
                className="
                    rounded-2xl border border-slate-200/80 dark:border-gray-800
                    bg-white dark:bg-gray-900 p-5 relative overflow-hidden
                    transition-colors duration-200
                    group-hover:border-slate-300 dark:group-hover:border-gray-700
                "
            >
            <div
                className="absolute top-0 left-0 w-full h-1 transition-opacity group-hover:opacity-100 opacity-90"
                style={{ backgroundColor: accent }}
            />
            <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-gray-500">
                {label}
            </p>
            <p className="text-3xl font-bold text-slate-900 dark:text-gray-50 mt-2 tabular-nums leading-tight">
                {value}
            </p>
            {hint && (
                <p className="text-xs text-slate-500 dark:text-gray-500 mt-2 line-clamp-2">{hint}</p>
            )}
            </div>
        </div>
    );
}
