import React, { useMemo, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import { SurfaceCard } from "@/components/ui/SurfaceCard";
import { OnboardingProgress } from "@/components/OnboardingProgress";
import { Step1Learning } from "@/components/Step1Learning";
import { Step2Domain } from "@/components/Step2Domain";
import { Step3Persona } from "@/components/Step3Persona";
import { Step4Goal } from "@/components/Step4Goal";
import { IconArrowLeft, IconArrowRight } from "@/components/OnboardingIcons";
import {
    CareerGoal,
    LearningPreference,
    MentorPersona,
    OnboardingAnswers,
} from "@/types/OnboardingTypes";
import {
    domainLabel,
    GOAL_LABELS,
    LEARNING_LABELS,
    PERSONA_LABELS,
} from "@/lib/onboardingLabels";

const STEPS = [
    { id: 1, label: "Формат", title: "Как вам комфортнее учиться?", subtitle: "Влияет на подачу материала в уроках." },
    { id: 2, label: "Домен", title: "Сфера применения языка", subtitle: "Контекст задач и примеров в курсе." },
    { id: 3, label: "Ментор", title: "Стиль обратной связи", subtitle: "Тональность код-ревью от ИИ." },
    { id: 4, label: "Цель", title: "Главная цель обучения", subtitle: "Акценты в мотивации и сложности." },
];

interface CourseSettingsProps {
    course: {
        id: number;
        title: string;
        direction: string;
        level: string;
    };
    preferences: {
        style: LearningPreference;
        domain: string | null;
        persona: MentorPersona;
        goal: CareerGoal | null;
    } | null;
    [key: string]: unknown;
}

export default function CourseSettings() {
    const { course, preferences } = usePage<CourseSettingsProps>().props;
    const direction = course.direction;

    const initialAnswers = useMemo<OnboardingAnswers>(
        () => ({
            learning_preference: preferences?.style ?? "balanced",
            domain_interest: preferences?.domain ?? null,
            mentor_persona: preferences?.persona ?? "colleague",
            career_goal: preferences?.goal ?? null,
        }),
        [preferences],
    );

    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [answers, setAnswers] = useState<OnboardingAnswers>(initialAnswers);

    const currentMeta = STEPS[step - 1];

    const canProceed = (): boolean => {
        if (step === 1) return answers.learning_preference !== null;
        if (step === 2) return answers.domain_interest !== null;
        if (step === 3) return answers.mentor_persona !== null;
        if (step === 4) return answers.career_goal !== null;
        return false;
    };

    const handleSave = () => {
        if (!canProceed()) return;
        setSaving(true);
        router.put(
            `/courses/${course.id}/settings`,
            {
                learning_preferences: {
                    style: answers.learning_preference,
                    domain: answers.domain_interest,
                    persona: answers.mentor_persona,
                    goal: answers.career_goal,
                },
            },
            {
                onFinish: () => setSaving(false),
            },
        );
    };

    if (!preferences) {
        return (
            <AppLayout>
                <Head title="Настройки курса" />
                <div className="px-8 py-16 text-center">
                    <p className="text-slate-600">Профиль обучения не найден для этого курса.</p>
                    <button
                        type="button"
                        onClick={() => router.visit("/main")}
                        className="mt-4 text-violet-600 font-semibold text-sm"
                    >
                        Вернуться к курсам
                    </button>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title={`Настройки · ${course.title}`} />

            <div className="px-6 lg:px-10 pb-16 max-w-3xl mx-auto">
                <Breadcrumbs
                    crumbs={[
                        { label: "DevPath", path: "/main" },
                        { label: "Мои курсы", path: "/main" },
                        { label: "Настройки", path: `/courses/${course.id}/settings` },
                    ]}
                />

                <header className="mb-8 mt-4">
                    <p className="text-xs font-bold text-violet-500 uppercase tracking-widest mb-2">
                        {course.direction} · {course.level}
                    </p>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-gray-50">{course.title}</h1>
                    <p className="text-sm text-slate-500 mt-2">
                        Текущие настройки персонализации. Измените параметры — они применятся к следующим урокам.
                    </p>
                </header>

                <div className="mb-8 surface-elevated rounded-2xl">
                <div className="rounded-2xl border border-slate-200 dark:border-gray-800 bg-slate-50/80 dark:bg-gray-900/50 p-5 text-sm overflow-hidden">
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Сейчас выбрано</p>
                    <dl className="grid sm:grid-cols-2 gap-3 text-slate-700 dark:text-gray-300">
                        <div>
                            <dt className="text-xs text-slate-400">Формат</dt>
                            <dd className="font-medium">
                                {answers.learning_preference ? LEARNING_LABELS[answers.learning_preference] : "—"}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Домен</dt>
                            <dd className="font-medium">{domainLabel(direction, answers.domain_interest)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Ментор</dt>
                            <dd className="font-medium">
                                {answers.mentor_persona ? PERSONA_LABELS[answers.mentor_persona] : "—"}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Цель</dt>
                            <dd className="font-medium">
                                {answers.career_goal ? GOAL_LABELS[answers.career_goal] : "—"}
                            </dd>
                        </div>
                    </dl>
                </div>
                </div>

                <div className="mb-6">
                    <OnboardingProgress current={step} total={4} labels={STEPS.map((s) => s.label)} />
                </div>

                <SurfaceCard className="rounded-3xl" innerClassName="rounded-3xl overflow-hidden">
                    <div className="px-7 pt-7 pb-5 border-b border-slate-100 dark:border-gray-800">
                        <span className="text-xs font-bold text-violet-500 uppercase tracking-widest">
                            Шаг {step} из 4
                        </span>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-gray-100 mt-1">{currentMeta.title}</h2>
                        <p className="text-xs text-slate-400 mt-1">{currentMeta.subtitle}</p>
                    </div>

                    <div className="px-7 py-5">
                        {step === 1 && (
                            <Step1Learning
                                value={answers.learning_preference}
                                onChange={(v) => setAnswers((a) => ({ ...a, learning_preference: v }))}
                            />
                        )}
                        {step === 2 && (
                            <Step2Domain
                                direction={direction}
                                value={answers.domain_interest}
                                onChange={(v) => setAnswers((a) => ({ ...a, domain_interest: v }))}
                            />
                        )}
                        {step === 3 && (
                            <Step3Persona
                                value={answers.mentor_persona}
                                onChange={(v) => setAnswers((a) => ({ ...a, mentor_persona: v }))}
                            />
                        )}
                        {step === 4 && (
                            <Step4Goal
                                value={answers.career_goal}
                                onChange={(v) => setAnswers((a) => ({ ...a, career_goal: v }))}
                            />
                        )}
                    </div>

                    <div className="px-7 pb-7 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            onClick={() => (step > 1 ? setStep((s) => s - 1) : router.visit("/main"))}
                            className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-50 dark:hover:bg-gray-800 rounded-xl"
                        >
                            <IconArrowLeft className="w-4 h-4" />
                            {step === 1 ? "К курсам" : "Назад"}
                        </button>
                        <button
                            type="button"
                            onClick={() => (step < 4 ? setStep((s) => s + 1) : handleSave())}
                            disabled={!canProceed() || saving}
                            className={`flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all ${
                                canProceed() && !saving
                                    ? "bg-violet-600 hover:bg-orange-500 text-white shadow-md"
                                    : "bg-slate-100 text-slate-400 cursor-not-allowed"
                            }`}
                        >
                            {saving ? (
                                <>
                                    <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                                    Сохранение…
                                </>
                            ) : step === 4 ? (
                                "Сохранить настройки"
                            ) : (
                                <>
                                    Далее
                                    <IconArrowRight className="w-4 h-4" />
                                </>
                            )}
                        </button>
                    </div>
                </SurfaceCard>
            </div>
        </AppLayout>
    );
}
