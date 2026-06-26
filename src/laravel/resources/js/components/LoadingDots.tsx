import React from "react";

/** Три точки ожидания — для «Определяется», генерации вопроса и т.п. */
export function LoadingDots({ className = "" }: { className?: string }) {
    return (
        <span className={`loading-dots inline-flex items-center gap-[3px] ml-1 ${className}`} aria-hidden>
            <span className="loading-dot" />
            <span className="loading-dot" />
            <span className="loading-dot" />
        </span>
    );
}
