import React, { useId, useState } from "react";

export function InfoTooltip({ text }: { text: string }) {
    const [open, setOpen] = useState(false);
    const id = useId();

    return (
        <span className="relative inline-flex align-middle ml-1">
            <button
                type="button"
                aria-describedby={open ? id : undefined}
                className="w-4 h-4 rounded-full border border-slate-300 dark:border-gray-600 text-[10px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-gray-800 transition-colors"
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
            >
                i
            </button>
            {open && (
                <span
                    id={id}
                    role="tooltip"
                    className="absolute z-20 left-1/2 -translate-x-1/2 bottom-full mb-2 w-56 px-3 py-2 text-xs text-left rounded-lg bg-slate-900 text-white shadow-lg dark:bg-gray-800"
                >
                    {text}
                </span>
            )}
        </span>
    );
}
