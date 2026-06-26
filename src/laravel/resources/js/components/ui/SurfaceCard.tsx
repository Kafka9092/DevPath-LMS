import React from "react";

export function SurfaceCard({
    children,
    className = "",
    innerClassName = "",
}: {
    children: React.ReactNode;
    className?: string;
    innerClassName?: string;
}) {
    return (
        <div className={`rounded-2xl surface-elevated ${className}`}>
            <div
                className={`rounded-2xl border border-slate-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-gray-900 ${innerClassName}`}
            >
                {children}
            </div>
        </div>
    );
}
