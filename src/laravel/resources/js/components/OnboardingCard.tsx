import React from "react";
import { IconCheck } from "./OnboardingIcons";

interface OnboardingCardProps {
    selected: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    label: string;
    description: string;
    accent?: "violet" | "orange";
    disabled?: boolean;
}

export const OnboardingCard: React.FC<OnboardingCardProps> = ({
    selected,
    onClick,
    icon,
    label,
    description,
    disabled = false,
}) => {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className={`
                group relative w-full text-left px-5 py-4 rounded-2xl border-2
                transition-all duration-200 active:scale-[0.98]
                focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2
                ${selected
                    ? "border-violet-500 bg-violet-600 text-white shadow-lg shadow-violet-200"
                    : "border-slate-300 bg-white dark:border-gray-700 dark:bg-gray-900 text-slate-800 dark:text-gray-100 surface-control hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/60 dark:hover:bg-violet-950/30"
                }
                ${disabled ? "opacity-50 cursor-not-allowed" : "cursor-pointer"}
            `}
        >
            <div className="flex items-start gap-4">
                {}
                <div className={`
                    shrink-0 w-10 h-10 rounded-xl flex items-center justify-center mt-0.5
                    transition-colors duration-200
                    ${selected
                        ? "bg-white/20 text-white"
                        : "bg-violet-50 text-violet-500 group-hover:bg-violet-100"
                    }
                `}>
                    {icon}
                </div>

                {}
                <div className="flex-1 min-w-0">
                    <p className={`font-semibold text-sm leading-snug mb-0.5 ${selected ? "text-white" : "text-slate-900 dark:text-gray-100"}`}>
                        {label}
                    </p>
                    <p className={`text-xs leading-relaxed ${selected ? "text-violet-200" : "text-slate-400 dark:text-gray-500"}`}>
                        {description}
                    </p>
                </div>

                {}
                <div className={`
                    shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center mt-0.5
                    transition-all duration-200
                    ${selected
                        ? "border-orange-400 bg-orange-500"
                        : "border-slate-300 dark:border-gray-600 group-hover:border-violet-300 dark:group-hover:border-violet-600"
                    }
                `}>
                    {selected && <IconCheck className="w-3 h-3 text-white" />}
                </div>
            </div>
        </button>
    );
};
