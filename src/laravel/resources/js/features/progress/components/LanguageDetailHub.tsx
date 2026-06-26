import React from "react";
import { BookOpen, Code2, Lock, Mic } from "lucide-react";
import { Link } from "@inertiajs/react";
import type { LanguageStat } from "@/types/progress";
import { hasLanguageActivity } from "../lib/languageProgressIndex";
import { MODULE_CHART_COLORS } from "../constants/moduleThemes";

function ModuleBlock({
    icon: Icon,
    title,
    color,
    stats,
}: {
    icon: React.ElementType;
    title: string;
    color: string;
    stats: { label: string; value: string | number; bar?: number; barMax?: number }[];
}) {
    return (
        <section
            className="rounded-xl border border-slate-100 dark:border-gray-800 border-l-4 bg-white dark:bg-gray-900 p-4 relative overflow-hidden"
            style={{ borderLeftColor: color }}
        >
            <div className="flex items-center gap-2 mb-4">
                <Icon className="w-4 h-4" style={{ color }} />
                <h4 className="text-sm font-semibold text-slate-800 dark:text-gray-100">{title}</h4>
            </div>
            <div className="space-y-3">
                {stats.map((s) => (
                    <div key={s.label}>
                        <div className="flex justify-between text-sm mb-1">
                            <span className="text-slate-600 dark:text-gray-400">{s.label}</span>
                            <span className="font-semibold tabular-nums text-slate-900 dark:text-gray-100">{s.value}</span>
                        </div>
                        {s.bar != null && s.barMax != null && s.barMax > 0 && (
                            <div className="h-1.5 rounded-full bg-slate-200/80 dark:bg-gray-700 overflow-hidden">
                                <div
                                    className="h-full rounded-full"
                                    style={{ width: `${(s.bar / s.barMax) * 100}%`, background: color }}
                                />
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </section>
    );
}

function LanguageEmptyState({ language }: { language: string }) {
    return (
        <div className="rounded-2xl surface-elevated">
        <div className="rounded-2xl border border-dashed border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 px-8 py-16 text-center overflow-hidden">
            <div className="mx-auto w-16 h-16 rounded-2xl bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 flex items-center justify-center text-slate-400 mb-6 shadow-sm">
                <Lock className="w-7 h-7" strokeWidth={1.5} />
            </div>
            <h3 className="text-xl font-semibold text-slate-900 dark:text-gray-100">
                По языку {language} пока нет данных
            </h3>
            <p className="text-sm text-slate-500 dark:text-gray-400 mt-3 max-w-md mx-auto leading-relaxed">
                Пройдите урок, собеседование или отправьте код на проверку — здесь появятся конкретные цифры.
            </p>
            <Link
                href="/main"
                className="inline-flex mt-8 items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold text-white bg-violet-600 hover:bg-orange-500 shadow-sm transition-colors"
            >
                Начать обучение
            </Link>
        </div>
        </div>
    );
}

export function LanguageDetailHub({ stat }: { stat: LanguageStat }) {
    const empty = !hasLanguageActivity(stat);

    if (empty) {
        return <LanguageEmptyState language={stat.language} />;
    }

    const lessonsProgress =
        stat.lessons_total > 0
            ? `${stat.lessons_completed} из ${stat.lessons_total}`
            : "—";

    return (
        <div className="rounded-2xl surface-elevated w-full">
        <div
            className="rounded-2xl border border-slate-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 lg:p-8 w-full overflow-hidden"
        >
            <h3 className="text-lg font-semibold text-slate-900 dark:text-gray-50 mb-1">
                {stat.language}
            </h3>
            <p className="text-sm text-slate-500 dark:text-gray-400 mb-6">
                Уроки, интервью и проверки кода по этому языку
            </p>

            <div className="grid md:grid-cols-3 gap-5">
                <ModuleBlock
                    icon={BookOpen}
                    title="Обучение"
                    color={MODULE_CHART_COLORS.learning}
                    stats={[
                        { label: "Начато уроков", value: stat.lessons_total },
                        { label: "Завершено", value: stat.lessons_completed, bar: stat.lessons_completed, barMax: stat.lessons_total },
                        { label: "Завершено из начатых", value: lessonsProgress },
                        {
                            label: "Средний балл за ДЗ",
                            value: stat.learning_score > 0 ? stat.learning_score : "—",
                            bar: stat.learning_score > 0 ? stat.learning_score : 0,
                            barMax: 100,
                        },
                    ]}
                />
                <ModuleBlock
                    icon={Mic}
                    title="Собеседования"
                    color={MODULE_CHART_COLORS.interviews}
                    stats={[
                        { label: "Пройдено", value: stat.interviews },
                        {
                            label: "Средняя оценка",
                            value: stat.interview_score > 0 ? `${stat.interview_score}/100` : "—",
                            bar: stat.interview_score > 0 ? stat.interview_score : 0,
                            barMax: 100,
                        },
                    ]}
                />
                <ModuleBlock
                    icon={Code2}
                    title="Code Review"
                    color={MODULE_CHART_COLORS.codeReview}
                    stats={[
                        { label: "Проверок", value: stat.code_reviews },
                        {
                            label: "Средний балл",
                            value: stat.review_score > 0 ? `${stat.review_score}/100` : "—",
                            bar: stat.review_score > 0 ? stat.review_score : 0,
                            barMax: 100,
                        },
                    ]}
                />
            </div>
        </div>
        </div>
    );
}
