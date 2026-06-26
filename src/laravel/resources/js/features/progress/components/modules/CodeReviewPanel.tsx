import React, { useMemo } from "react";
import type { CodeReviewStats } from "@/types/progress";
import type { ModuleTheme } from "../../constants/moduleThemes";
import { EmptyState } from "../ui/EmptyState";
import { PanelCard } from "../ui/PanelCard";
import { CompletionRing, RankedBarChart } from "../charts";

function CodeIcon() {
    return (
        <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
        </svg>
    );
}

export function CodeReviewPanel({ data, theme }: { data: CodeReviewStats; theme: ModuleTheme }) {
    const byLanguage = useMemo(() => data.by_language ?? [], [data.by_language]);
    const s = data.summary;

    const topByReviews = useMemo(
        () =>
            byLanguage
                .filter((r) => Number(r.total ?? 0) > 0)
                .map((r) => ({
                    label: String(r.language),
                    value: Number(r.avg_score ?? 0),
                    sublabel: `${r.total} проверок · лучший ${r.best ?? "—"}`,
                }))
                .sort((a, b) => b.value - a.value),
        [byLanguage],
    );

    const topByCount = useMemo(
        () =>
            byLanguage
                .filter((r) => Number(r.total ?? 0) > 0)
                .map((r) => ({
                    label: String(r.language),
                    value: Number(r.total ?? 0),
                    sublabel: r.avg_score != null ? `ср. балл ${r.avg_score}` : undefined,
                }))
                .sort((a, b) => b.value - a.value),
        [byLanguage],
    );

    if (s.total === 0) {
        return (
            <div style={{ ["--empty-cta" as string]: theme.primary }}>
                <EmptyState
                    icon={<CodeIcon />}
                    title="Вы ещё не отправляли код на ревью"
                    description="Здесь будет: сколько проверок, средний балл и разбивка по языкам."
                    actionLabel="Отправить код на проверку"
                    actionHref="/code-review"
                />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="grid lg:grid-cols-3 gap-6">
                <PanelCard title="Проверки ≥ 70" accent={theme.primary}>
                    <div className="flex flex-col items-center py-2">
                        <CompletionRing
                            value={s.high_score_count}
                            max={s.total}
                            label={`${s.high_score_count} из ${s.total} проверок`}
                            color={theme.primary}
                        />
                        <p className="text-sm text-slate-600 dark:text-gray-400 mt-4 text-center">
                            Средний: <strong>{s.avg_score ?? "—"}</strong>
                            {" · "}
                            Лучший: <strong>{s.best_score ?? "—"}</strong>
                        </p>
                    </div>
                </PanelCard>

                <PanelCard title="Средний балл по языкам" subtitle="Шкала 0–100" accent={theme.primary} className="lg:col-span-2">
                    <RankedBarChart items={topByReviews} color={theme.primary} valueSuffix="" maxItems={6} />
                </PanelCard>
            </div>

            <PanelCard title="Число проверок по языкам" accent={theme.primary}>
                <RankedBarChart items={topByCount} color={theme.primary} />
                {s.total_issues > 0 && (
                    <p className="text-xs text-slate-500 mt-6 pt-4 border-t border-slate-100 dark:border-gray-800">
                        Замечаний в коде всего: <strong>{s.total_issues}</strong>
                        {s.issues_per_100_loc != null && ` (${s.issues_per_100_loc} на 100 строк)`}
                    </p>
                )}
            </PanelCard>
        </div>
    );
}
