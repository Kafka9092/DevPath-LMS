import React, { useEffect, useMemo, useState } from "react";
import { Link, router, usePage } from '@inertiajs/react';
import { LevelSelector } from "@/components/LevelSelector";
import { NextButtonPlanSetting } from "@/components/NextButtonPlanSetting";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import { buildSeniorHubUrl, markSeniorModeTried } from "@/lib/seniorProgramStorage";
import { abandonStoredTest } from "@/lib/adaptiveTestStorage";
import { planSettingsBreadcrumbs } from "@/lib/courseFlowBreadcrumbs";
import { SurfaceCard } from "@/components/ui/SurfaceCard";

interface PlanSettingsProps {
    direction?: string;
    competences?: Record<string, number>;
    strong_topics?: string[];
    weak_topics?: string[];
    recommendation?: string;
    realLevel?: string;
    from_test?: boolean;
    [key: string]: unknown;
}

const LEVELS = ['beginner', 'junior', 'middle', 'senior'] as const;

const LEVEL_LABELS: Record<string, string> = {
    beginner: 'Beginner',
    junior: 'Junior',
    middle: 'Middle',
    senior: 'Senior',
};

function levelIndex(level: string): number {
    return LEVELS.indexOf(level as (typeof LEVELS)[number]);
}

function buildLevelAdvice(realLevel: string, selectedLevel: string | null): string | null {
    if (!selectedLevel || !realLevel) return null;

    const diff = levelIndex(selectedLevel) - levelIndex(realLevel);
    const real = LEVEL_LABELS[realLevel] ?? realLevel;
    const selected = LEVEL_LABELS[selectedLevel] ?? selectedLevel;

    if (diff === 0) {
        return `Уровень ${selected} совпадает с результатом теста (${real}) — оптимальный выбор для старта.`;
    }
    if (diff === 1) {
        return `Ваш уровень по тесту: ${real}. Курс ${selected} будет на шаг сложнее — это допустимо, если готовы к более интенсивному темпу.`;
    }
    if (diff >= 2) {
        return `Ваш уровень по тесту: ${real}. Курс ${selected} будет заметно сложнее рекомендуемого — мы советуем уровень ближе к ${real}, но окончательный выбор за вами.`;
    }
    if (diff === -1) {
        return `Курс ${selected} мягче, чем показал тест (${real}) — подойдёт для спокойного закрепления базы.`;
    }
    return `Курс ${selected} проще вашего уровня по тесту (${real}) — удобно для разогрева, без лишнего давления.`;
}

export default function PlanSettings() {
    const {
        direction = 'PHP',
        realLevel,
        from_test: fromTest = false,
    } = usePage<PlanSettingsProps>().props;

    const [selectedLevel, setSelectedLevel] = useState<string | null>(
        fromTest && realLevel ? realLevel : null,
    );

    const levelAdvice = useMemo(
        () => (fromTest && realLevel ? buildLevelAdvice(realLevel, selectedLevel) : null),
        [fromTest, realLevel, selectedLevel],
    );

    const isSeniorTest = (realLevel ?? "").toLowerCase() === "senior";

    useEffect(() => {
        if (isSeniorTest) {
            markSeniorModeTried(direction, "learning");
        }
    }, [isSeniorTest, direction]);

    const handleSubmit = () => {
        if (!selectedLevel) {
            alert('Выберите уровень сложности обучения');
            return;
        }

        abandonStoredTest();
        router.post('/plan/select-level', {
            direction,
            level: selectedLevel,
        });
    };

    return (
        <AppLayout>
            <div className="w-full px-6 lg:px-10">
                <Breadcrumbs crumbs={planSettingsBreadcrumbs(direction)} />
            </div>
            <div className="flex flex-1 w-full flex-col items-center justify-center px-6 py-16">
                <div className="w-full max-w-2xl">
                    {isSeniorTest && (
                        <div className="mb-6 text-center">
                            <Link
                                href={buildSeniorHubUrl(direction)}
                                className="text-sm font-medium text-violet-600 hover:text-violet-800"
                            >
                                ← Все режимы Senior
                            </Link>
                        </div>
                    )}
                    <p className="text-sm font-medium text-slate-400 dark:text-gray-500 uppercase tracking-wider mb-8 text-center">
                        ПЛАН ОБУЧЕНИЯ · {direction.toUpperCase()}
                    </p>

                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-extrabold text-slate-800 dark:text-gray-100 leading-tight mb-3">
                            Создание{' '}
                            <span className="text-violet-600">плана обучения</span>
                        </h1>
                        <p className="text-base text-slate-500 dark:text-gray-400">
                            {fromTest
                                ? 'На основе теста выберите целевой уровень программы'
                                : 'Выберите уровень сложности курса — система не ограничивает ваш выбор'}
                        </p>
                    </div>

                    {fromTest && realLevel && (
                        <SurfaceCard className="mb-8" innerClassName="border-2 border-violet-500 p-5 text-center">
                            <p className="text-base text-slate-700 dark:text-gray-200">
                                Ваш текущий уровень:{' '}
                                <strong className="text-violet-700 dark:text-violet-400">
                                    {LEVEL_LABELS[realLevel] ?? realLevel}
                                </strong>
                            </p>
                        </SurfaceCard>
                    )}

                    <div className="mb-10">
                        <LevelSelector
                            levels={[...LEVELS]}
                            selectedLevel={selectedLevel}
                            onLevelSelect={setSelectedLevel}
                        />
                    </div>

                    {selectedLevel && levelAdvice && (
                        <div className="animate-[fadeUp_0.3s_ease_both] mb-8">
                            <SurfaceCard
                                innerClassName={`border-2 p-5 ${
                                    realLevel && levelIndex(selectedLevel) > levelIndex(realLevel) + 1
                                        ? 'border-orange-500'
                                        : 'border-violet-500'
                                }`}
                            >
                                <p className="text-slate-700 dark:text-gray-300 text-sm leading-relaxed">{levelAdvice}</p>
                            </SurfaceCard>
                        </div>
                    )}

                    {selectedLevel && !fromTest && (
                        <div className="animate-[fadeUp_0.3s_ease_both] mb-8">
                            <SurfaceCard innerClassName="border-2 border-violet-500 p-5">
                                <p className="text-slate-700 dark:text-gray-300 text-sm">
                                    Вы выбрали уровень{' '}
                                    <strong className="text-violet-700 dark:text-violet-400">
                                        {LEVEL_LABELS[selectedLevel] ?? selectedLevel}
                                    </strong>
                                    . Далее настроим стиль обучения и сгенерируем план.
                                </p>
                            </SurfaceCard>
                        </div>
                    )}

                    <NextButtonPlanSetting onClick={handleSubmit} disabled={!selectedLevel} />
                </div>
            </div>

            <style>{`
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </AppLayout>
    );
}
