import React, { useMemo } from "react";
import type { InterviewStats } from "@/types/progress";
import type { ModuleTheme } from "../../constants/moduleThemes";
import { EmptyState } from "../ui/EmptyState";
import { PanelCard } from "../ui/PanelCard";
import { RankedBarChart, StarScoreBars, VerdictStrip } from "../charts";

function MicIcon() {
    return (
        <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m12 0v3.75m-12 0V18a6 6 0 006 6v0m6-12.75a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
        </svg>
    );
}

export function InterviewsPanel({ data, theme }: { data: InterviewStats; theme: ModuleTheme }) {
    const byLanguage = useMemo(() => data.by_language ?? [], [data.by_language]);
    const byLevel = useMemo(() => data.by_level.filter((r) => r.total > 0), [data.by_level]);
    const weakTopics = data.summary.weak_topics ?? [];
    const s = data.summary;
    const otherVerdicts = Math.max(0, s.completed - s.successful - s.rejected);

    const topByInterviews = useMemo(
        () =>
            byLanguage
                .filter((r) => Number(r.total ?? 0) > 0)
                .map((r) => ({
                    label: String(r.language),
                    value: Number(r.total ?? 0),
                    sublabel: `${r.successful ?? 0} принято · ${r.success_rate ?? 0}%`,
                }))
                .sort((a, b) => b.value - a.value),
        [byLanguage],
    );

    if (s.total === 0) {
        return (
            <div style={{ ["--empty-cta" as string]: theme.primary }}>
                <EmptyState
                    icon={<MicIcon />}
                    title="Вы ещё не проходили AI-собеседования"
                    description="Здесь будет видно: сколько интервью пройдено, вердикты HR, средняя длительность и слабые темы."
                    actionLabel="Пройти первое собеседование"
                    actionHref="/ai-hr"
                />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="grid lg:grid-cols-2 gap-6">
                <PanelCard title="Вердикты HR" subtitle="По завершённым собеседованиям" accent={theme.primary}>
                    <VerdictStrip successful={s.successful} rejected={s.rejected} other={otherVerdicts} />
                    <div className="grid grid-cols-2 gap-4 mt-6 pt-4 border-t border-slate-100 dark:border-gray-800">
                        <div>
                            <p className="text-xs text-slate-500 uppercase">Всего</p>
                            <p className="text-2xl font-bold tabular-nums">{s.total}</p>
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 uppercase">Завершено</p>
                            <p className="text-2xl font-bold tabular-nums" style={{ color: theme.primary }}>{s.completed}</p>
                        </div>
                    </div>
                    <p className="text-xs text-slate-500 mt-4">
                        {s.avg_duration_min != null && `Средняя длительность: ${s.avg_duration_min} мин. `}
                        {s.ai_hard_skills_score != null && `Hard skills: ${s.ai_hard_skills_score}/100.`}
                    </p>
                </PanelCard>

                {data.star_scores.some((x) => x.avg > 0) ? (
                    <PanelCard title="STAR-оценки" subtitle="Среднее из 5 по завершённым" accent={theme.primary}>
                        <StarScoreBars scores={data.star_scores} color={theme.primary} />
                    </PanelCard>
                ) : (
                    <PanelCard title="Сводка" accent={theme.primary}>
                        <p className="text-sm text-slate-600 dark:text-gray-400 leading-relaxed">
                            Досрочно остановлено: <strong>{s.stopped_early}</strong>.
                            {s.code_tasks > 0 && ` Код-задач: ${s.code_tasks}.`}
                        </p>
                    </PanelCard>
                )}
            </div>

            {weakTopics.length > 0 && (
                <div className={`rounded-2xl border p-5 ${theme.bgSoft} border-slate-200/80 dark:border-gray-800`}>
                    <h3 className="text-sm font-semibold text-slate-800 dark:text-gray-100 mb-3">Слабые темы из вердиктов HR</h3>
                    <div className="flex flex-wrap gap-2">
                        {weakTopics.map((t) => (
                            <span
                                key={t}
                                className="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium border"
                                style={{ borderColor: theme.primary, color: theme.primary, background: `${theme.primary}12` }}
                            >
                                {t}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <div className="grid lg:grid-cols-2 gap-6">
                <PanelCard title="Топ языков" subtitle="По числу интервью" accent={theme.primary}>
                    <RankedBarChart items={topByInterviews} color={theme.primary} />
                </PanelCard>

                {byLevel.length > 0 && (
                    <PanelCard title="По уровню позиции" accent={theme.primary}>
                        <RankedBarChart
                            items={byLevel.map((r) => ({
                                label: String(r.level),
                                value: r.total,
                                sublabel: `${r.completed} завершено`,
                            }))}
                            color={theme.primary}
                        />
                    </PanelCard>
                )}
            </div>
        </div>
    );
}
