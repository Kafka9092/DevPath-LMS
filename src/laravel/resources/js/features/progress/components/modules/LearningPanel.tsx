import React, { useMemo } from "react";
import type { LearningStats } from "@/types/progress";
import type { ModuleTheme } from "../../constants/moduleThemes";
import { EmptyState } from "../ui/EmptyState";
import { PanelCard } from "../ui/PanelCard";
import { CompletionRing, RankedBarChart, ScoreBucketBars } from "../charts";

function BookIcon() {
    return (
        <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    );
}

export function LearningPanel({ data, theme }: { data: LearningStats; theme: ModuleTheme }) {
    const byLanguage = useMemo(() => data.by_language ?? [], [data.by_language]);
    const hasData = data.summary.lessons_touched > 0;

    const topByLessons = useMemo(
        () =>
            byLanguage
                .filter((r) => Number(r.lessons_completed ?? 0) > 0)
                .map((r) => ({
                    label: String(r.language),
                    value: Number(r.lessons_completed ?? 0),
                    sublabel: r.avg_score != null ? `ср. балл ${r.avg_score}` : undefined,
                }))
                .sort((a, b) => b.value - a.value),
        [byLanguage],
    );

    if (!hasData) {
        return (
            <EmptyState
                icon={<BookIcon />}
                title="Вы ещё не начинали обучение"
                description="Пройдите первый урок или создайте курс — здесь появятся факты: сколько уроков пройдено, баллы и подсказки."
                actionLabel="Перейти к обучению"
                actionHref="/main"
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="grid lg:grid-cols-3 gap-6">
                <PanelCard title="Прогресс уроков" accent={theme.primary}>
                    <div className="flex flex-col items-center py-2">
                        <CompletionRing
                            value={data.summary.lessons_completed}
                            max={data.summary.lessons_touched}
                            label={`${data.summary.lessons_completed} из ${data.summary.lessons_touched} уроков`}
                            color={theme.primary}
                        />
                        <p className="text-sm text-slate-600 dark:text-gray-400 mt-4 text-center">
                            Средний балл: <strong>{data.summary.avg_score ?? "—"}</strong>
                            {" · "}
                            Подсказок: <strong>{data.summary.hints_used}</strong>
                        </p>
                    </div>
                </PanelCard>

                <PanelCard title="Распределение оценок" subtitle="Сколько уроков в каждом диапазоне баллов" accent={theme.primary} className="lg:col-span-2">
                    <ScoreBucketBars buckets={data.score_buckets} color={theme.primary} />
                </PanelCard>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                <PanelCard title="Топ языков по урокам" subtitle="Завершённые уроки" accent={theme.primary}>
                    <RankedBarChart items={topByLessons} color={theme.primary} maxItems={6} />
                </PanelCard>

                <PanelCard title="Все языки" subtitle="Завершено / начато" accent={theme.primary}>
                    <div className="space-y-3 max-h-80 overflow-y-auto pr-1">
                        {byLanguage.map((row) => {
                            const touched = Number(row.lessons_touched ?? 0);
                            const done = Number(row.lessons_completed ?? 0);
                            const inactive = touched === 0;
                            return (
                                <div
                                    key={String(row.language)}
                                    className={`rounded-xl px-3 py-2.5 ${inactive ? "opacity-40" : "bg-slate-50/80 dark:bg-gray-800/40"}`}
                                >
                                    <div className="flex justify-between text-sm mb-1.5">
                                        <span className="font-medium text-slate-800 dark:text-gray-200">{row.language}</span>
                                        <span className="tabular-nums text-slate-500">
                                            {done}/{touched || "—"}
                                        </span>
                                    </div>
                                    {!inactive && (
                                        <div className="h-2 rounded-full bg-slate-200/80 dark:bg-gray-700 overflow-hidden">
                                            <div
                                                className="h-full rounded-full transition-all"
                                                style={{
                                                    width: `${touched > 0 ? (done / touched) * 100 : 0}%`,
                                                    background: theme.primary,
                                                }}
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </PanelCard>
            </div>
        </div>
    );
}
