import React from "react";
import { BookOpen, Code2, Mic } from "lucide-react";
import { LanguageLogoBadge } from "@/components/course/LanguageLogoBadge";
import type { LanguageStat } from "@/types/progress";
import { hasLanguageActivity } from "../lib/languageProgressIndex";

function ActivityBadge({
    icon: Icon,
    count,
    label,
}: {
    icon: React.ElementType;
    count: number;
    label: string;
}) {
    return (
        <span className="inline-flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-gray-800 px-2 py-1 text-[10px] font-medium text-slate-600 dark:text-gray-300">
            <Icon className="w-3 h-3 shrink-0 opacity-70" strokeWidth={2} />
            <span className="tabular-nums">{count}</span>
            <span className="hidden sm:inline opacity-80">{label}</span>
        </span>
    );
}

export function LanguageStatCard({
    stat,
    isSelected,
    onSelect,
}: {
    stat: LanguageStat;
    isSelected: boolean;
    onSelect: () => void;
}) {
    const active = hasLanguageActivity(stat);
    const totalActions = stat.lessons_total + stat.interviews + stat.code_reviews;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={`
                group text-left rounded-2xl border p-4 transition-all duration-200 w-full
                ${isSelected
                    ? "border-blue-500/80 bg-blue-50/90 dark:bg-blue-950/50 shadow-inner ring-1 ring-blue-400/40 scale-[0.99]"
                    : "border-slate-200 dark:border-gray-800 bg-white dark:bg-gray-900 surface-elevated hover:border-blue-200 dark:hover:border-blue-900 hover:-translate-y-0.5"
                }
            `}
        >
            <div className="flex items-center gap-2.5 mb-3">
                <LanguageLogoBadge direction={stat.language} size={32} />
                <p className="font-semibold text-slate-900 dark:text-gray-50 truncate">{stat.language}</p>
            </div>

            <p className={`text-2xl font-bold tabular-nums leading-none ${active ? "text-blue-600 dark:text-blue-400" : "text-slate-300 dark:text-gray-600"}`}>
                {totalActions}
            </p>
            <p className="text-xs text-slate-500 mt-1">
                {active ? "действий всего" : "нет активности"}
            </p>

            <div className={`flex flex-wrap gap-1.5 mt-3 ${active ? "" : "opacity-60"}`}>
                <ActivityBadge icon={BookOpen} count={stat.lessons_completed} label="уроков" />
                <ActivityBadge icon={Mic} count={stat.interviews} label="собес." />
                <ActivityBadge icon={Code2} count={stat.code_reviews} label="ревью" />
            </div>
        </button>
    );
}
