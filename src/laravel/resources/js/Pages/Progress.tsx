import React from "react";
import { Head } from "@inertiajs/react";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import type { ProgressPageProps } from "@/types/progress";
import { useProgressDashboard } from "@/features/progress/hooks/useProgressDashboard";
import { StatCard } from "@/features/progress/components/ui/StatCard";
import { PanelCard } from "@/features/progress/components/ui/PanelCard";
import { ModuleTabNav } from "@/features/progress/components/ui/ModuleTabNav";
import { LanguageExplorer } from "@/features/progress/components/LanguageExplorer";
import { StackedLangActivityBars } from "@/features/progress/components/charts";
import { LearningPanel } from "@/features/progress/components/modules/LearningPanel";
import { InterviewsPanel } from "@/features/progress/components/modules/InterviewsPanel";
import { CodeReviewPanel } from "@/features/progress/components/modules/CodeReviewPanel";

export default function Progress(props: ProgressPageProps) {
    const {
        activeModule,
        setActiveModule,
        theme,
        themes,
        kpis,
        languageOverview,
    } = useProgressDashboard(props);

    const { languages, learning, interviews, codeReview } = props;
    const hasAnyLanguageActivity = languageOverview.some((r) => r.total > 0);

    return (
        <AppLayout>
            <Head title="Прогресс" />

            <div className="w-full flex justify-center">
                <div className="w-full max-w-[1400px] px-6 lg:px-10 pb-20">
                    <Breadcrumbs
                        crumbs={[
                            { label: "DevPath", path: "/main" },
                            { label: "Прогресс", path: "/progress" },
                        ]}
                    />

                    <header className="mb-10 text-center lg:text-left">
                        <h1 className="text-3xl font-semibold text-slate-900 dark:text-gray-50 tracking-tight">
                            Мой прогресс
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-gray-500 mt-2 max-w-3xl mx-auto lg:mx-0">
                            Факты из обучения, AI-собеседований и Code Review.
                        </p>
                    </header>

                    <LanguageExplorer languages={languages} />

                    <ModuleTabNav active={activeModule} onChange={setActiveModule} themes={themes} />

                    <section className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10 w-full">
                        {kpis.map((kpi) => (
                            <StatCard
                                key={kpi.label}
                                label={kpi.label}
                                value={kpi.value}
                                hint={kpi.hint}
                                accent={theme.primary}
                            />
                        ))}
                    </section>

                    <PanelCard
                        title="Активность по языкам"
                        subtitle="Длина полоски — сколько всего действий; цвета: уроки · собеседования · ревью"
                        accent="#6366f1"
                        className="mb-10"
                    >
                        {!hasAnyLanguageActivity ? (
                            <p className="text-sm text-slate-500 py-6 text-center">
                                Пока нет активности ни по одному языку
                            </p>
                        ) : (
                            <StackedLangActivityBars rows={languageOverview} />
                        )}
                    </PanelCard>

                    {activeModule === "learning" && <LearningPanel data={learning} theme={theme} />}
                    {activeModule === "interviews" && <InterviewsPanel data={interviews} theme={theme} />}
                    {activeModule === "codeReview" && <CodeReviewPanel data={codeReview} theme={theme} />}
                </div>
            </div>
        </AppLayout>
    );
}
