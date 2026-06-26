import React from "react";
import type { ModuleId, ModuleTheme } from "../../constants/moduleThemes";
import { MODULE_TABS } from "../../constants/moduleThemes";

export function ModuleTabNav({
    active,
    onChange,
    themes,
}: {
    active: ModuleId;
    onChange: (id: ModuleId) => void;
    themes: Record<ModuleId, ModuleTheme>;
}) {
    return (
        <div className="border-b border-slate-200 dark:border-gray-800 mb-8">
            <nav className="flex gap-1 -mb-px overflow-x-auto justify-center lg:justify-start">
                {MODULE_TABS.map((t) => {
                    const theme = themes[t.id];
                    const isActive = active === t.id;
                    return (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => onChange(t.id)}
                            className={`
                                px-5 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors
                                ${isActive ? "" : "border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-gray-200"}
                            `}
                            style={
                                isActive
                                    ? { borderColor: theme.primary, color: theme.primary }
                                    : undefined
                            }
                        >
                            {t.label}
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}
