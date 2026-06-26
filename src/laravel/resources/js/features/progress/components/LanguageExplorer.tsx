import React, { useMemo, useState } from "react";
import type { LanguageStat } from "@/types/progress";
import { LanguageStatCard } from "./LanguageStatCard";
import { LanguageDetailHub } from "./LanguageDetailHub";

function normalizeLanguageStat(raw: LanguageStat): LanguageStat {
    return {
        ...raw,
        learning_score: raw.learning_score ?? 0,
        interview_score: raw.interview_score ?? 0,
        review_score: raw.review_score ?? 0,
        grade: raw.grade ?? "Старт",
        has_activity:
            raw.has_activity ??
            ((raw.lessons_total ?? 0) > 0 || (raw.interviews ?? 0) > 0 || (raw.code_reviews ?? 0) > 0),
        kpi: raw.kpi ?? {
            time_label: "0ч",
            accuracy_pct: null,
            code_quality_label: null,
            market_readiness_pct: 0,
        },
        timeline: (raw.timeline ?? []).map((p) => ({
            ...p,
            tooltip: p.tooltip ?? `${p.label} — ${p.value}`,
        })),
    };
}

export const LanguageExplorer: React.FC<{ languages: LanguageStat[] }> = ({ languages: rawLanguages }) => {
    const languages = useMemo(() => rawLanguages.map(normalizeLanguageStat), [rawLanguages]);
    const [selected, setSelected] = useState<string | null>(languages[0]?.language ?? null);

    const active = useMemo(
        () => languages.find((l) => l.language === selected) ?? languages[0] ?? null,
        [languages, selected],
    );

    if (languages.length === 0) {
        return (
            <p className="text-sm text-slate-500 text-center py-10 mb-10">
                Начните курс, собеседование или проверку кода — здесь появится статистика по языкам.
            </p>
        );
    }

    return (
        <section className="mb-12 w-full">
            <div className="mb-6">
                <h2 className="text-base font-semibold text-slate-800 dark:text-gray-100">Статистика по языкам</h2>
                <p className="text-xs text-slate-500 mt-1">
                    Выберите язык — ниже появятся конкретные цифры: уроки, собеседования, проверки кода
                </p>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-8">
                {languages.map((lang) => (
                    <LanguageStatCard
                        key={lang.language}
                        stat={lang}
                        isSelected={lang.language === selected}
                        onSelect={() => setSelected(lang.language)}
                    />
                ))}
            </div>

            {active && <LanguageDetailHub stat={active} />}
        </section>
    );
};
