import React from "react";
import { SENIOR_MODES, navigateSeniorMode } from "@/components/senior/seniorModes";

interface SeniorModesGridProps {
    direction: string;
    compact?: boolean;
    accent?: "violet" | "orange";
}

export const SeniorModesGrid: React.FC<SeniorModesGridProps> = ({
    direction,
    compact = false,
    accent = "violet",
}) => {
    const hoverClass =
        accent === "orange"
            ? "hover:border-orange-400 hover:bg-orange-50/50 dark:hover:border-orange-500 dark:hover:bg-orange-950/20"
            : "hover:border-violet-400 hover:bg-violet-50/40 dark:hover:border-violet-500 dark:hover:bg-violet-950/20";

    return (
    <div className={`grid grid-cols-1 ${compact ? "sm:grid-cols-2" : "sm:grid-cols-2"} gap-3`}>
        {SENIOR_MODES.map((mode) => (
            <button
                key={mode.id}
                type="button"
                onClick={() => navigateSeniorMode(direction, mode.id)}
                className={`text-left px-4 py-4 rounded-xl border-2 border-slate-300 bg-white surface-control dark:border-gray-600 dark:bg-gray-800 transition-all duration-150 active:scale-[0.99] ${hoverClass}`}
            >
                <p className={`font-semibold text-slate-900 dark:text-gray-100 ${compact ? "text-sm" : "text-base"}`}>
                    {mode.title}
                </p>
                <p className="text-sm text-slate-500 dark:text-gray-400 mt-1.5 leading-snug">{mode.description}</p>
            </button>
        ))}
    </div>
    );
};
