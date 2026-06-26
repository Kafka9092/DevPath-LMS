import React from "react";

/** Чекбокс — тонкая фиолетовая галочка при checked. */
export function AnswerCheckbox({ checked }: { checked: boolean }) {
    return (
        <span
            className={`shrink-0 flex h-[18px] w-[18px] items-center justify-center rounded border transition-colors ${
                checked ? "border-violet-500 bg-white" : "border-slate-300 bg-white"
            }`}
            aria-hidden
        >
            {checked && (
                <svg
                    className="h-3 w-3 text-violet-600"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.25"
                >
                    <path d="M3.5 8.5 6.5 11.5 12.5 5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            )}
        </span>
    );
}
