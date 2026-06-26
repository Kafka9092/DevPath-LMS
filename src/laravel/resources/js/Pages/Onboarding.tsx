import React, { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import axios from "axios";

import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import { SurfaceCard } from "@/components/ui/SurfaceCard";
import { Step1Learning }      from "@/components/Step1Learning";
import { Step2Domain }        from "@/components/Step2Domain";
import { Step3Persona }       from "@/components/Step3Persona";
import { Step4Goal }          from "@/components/Step4Goal";
import { IconArrowLeft, IconArrowRight } from "@/components/OnboardingIcons";
import {
    OnboardingAnswers,
    LearningPreference,
    MentorPersona,
    CareerGoal,
} from "@/types/OnboardingTypes";
import { abandonStoredTest } from "@/lib/adaptiveTestStorage";
import { clearLessonWorkspaceForCourse } from "@/lib/lessonWorkspaceStorage";
import { clearWorkspaceSession } from "@/lib/workspaceSession";
import { onboardingBreadcrumbs } from "@/lib/courseFlowBreadcrumbs";
import { OnboardingProgress } from "@/components/OnboardingProgress";


const STEPS = [
    {
        id:          1,
        label:       "Формат",
        title:       "Как тебе комфортнее учиться?",
        subtitle:    "Это влияет на то, как ИИ будет подавать материал внутри каждой темы курса.",
    },
    {
        id:          2,
        label:       "Домен",
        title:       "В какой сфере ты хочешь применять этот язык?",
        subtitle:    "ИИ будет генерировать задачи в контексте выбранной сферы — это делает обучение в разы интереснее.",
    },
    {
        id:          3,
        label:       "Ментор",
        title:       "Какой стиль код-ревью ты предпочитаешь?",
        subtitle:    "Выбранная роль определяет тональность обратной связи от ИИ на протяжении всего курса.",
    },
    {
        id:          4,
        label:       "Цель",
        title:       "Какая твоя главная цель?",
        subtitle:    "ИИ будет использовать это для мотивации и расстановки акцентов в обучении.",
    },
];


interface OnboardingProps {
    direction?: string;
    level?: string;
    competences?: Record<string, number>;
    weak_topics?: string[];
    strong_topics?: string[];
    realLevel?: string;
    [key: string]: unknown;
}

export default function Onboarding() {
    const props = usePage<OnboardingProps>().props;
    const direction = props.direction ?? "PHP";

    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [answers, setAnswers] = useState<OnboardingAnswers>({
        learning_preference: null,
        domain_interest:     null,
        mentor_persona:      null,
        career_goal:         null,
    });

    const currentMeta = STEPS[step - 1];

    const canProceed = (): boolean => {
        if (step === 1) return answers.learning_preference !== null;
        if (step === 2) return answers.domain_interest     !== null;
        if (step === 3) return answers.mentor_persona      !== null;
        if (step === 4) return answers.career_goal         !== null;
        return false;
    };

    const handleNext = () => {
        if (!canProceed()) return;
        if (step < 4) {
            setStep(s => s + 1);
        } else {
            handleSubmit();
        }
    };

    const handleBack = () => {
        if (step > 1) setStep(s => s - 1);
    };

    const handleSubmit = async () => {
        setLoading(true);
        try {
            const { data } = await axios.post("/generate-plan", {
                learning_preferences: {
                    style:   answers.learning_preference,
                    domain:  answers.domain_interest,
                    persona: answers.mentor_persona,
                    goal:    answers.career_goal,
                },
            });

            if (data.redirect_url) {
                abandonStoredTest();
                router.visit(data.redirect_url);
            } else if (data.course_id) {
                abandonStoredTest();
                clearWorkspaceSession(data.course_id);
                clearLessonWorkspaceForCourse(data.course_id);
                router.visit(`/workspace/${data.course_id}`);
            } else {
                abandonStoredTest();
                router.visit("/main");
            }
        } catch (err) {
            console.error(err);
            alert("Не удалось создать курс. Попробуйте ещё раз.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Персонализация обучения" />

            <div className="w-full px-6 lg:px-10">
                <Breadcrumbs crumbs={onboardingBreadcrumbs(direction)} />
            </div>

            <div className="flex min-h-[calc(100vh-88px)] items-center justify-center bg-white px-4 py-10 dark:bg-gray-950">
            <div className="w-full max-w-lg">

                {}
                <div className="text-center mb-8">
                    <p className="text-xs font-bold text-orange-500 uppercase tracking-widest mb-2">
                        Настройка курса · {direction}
                    </p>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-gray-100 tracking-tight">
                        Персонализация обучения
                    </h1>
                    <p className="text-sm text-slate-400 dark:text-gray-500 mt-1.5">
                        4 вопроса — и ИИ настроит курс под тебя
                    </p>
                </div>

                {}
                <div className="mb-8">
                    <OnboardingProgress
                        current={step}
                        total={4}
                        labels={STEPS.map(s => s.label)}
                    />
                </div>

                {}
                <SurfaceCard className="rounded-3xl" innerClassName="rounded-3xl overflow-hidden">

                    {}
                    <div className="px-7 pt-7 pb-5 border-b border-slate-100 dark:border-gray-800">
                        <div className="flex items-center gap-2 mb-2">
                            <span className="text-xs font-bold text-violet-500 uppercase tracking-widest">
                                Шаг {step} из 4
                            </span>
                        </div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-gray-100 leading-snug">
                            {currentMeta.title}
                        </h2>
                        <p className="text-xs text-slate-400 mt-1.5 leading-relaxed">
                            {currentMeta.subtitle}
                        </p>
                    </div>

                    {}
                    <div className="px-7 py-5">
                        {step === 1 && (
                            <Step1Learning
                                value={answers.learning_preference}
                                onChange={v => setAnswers(a => ({ ...a, learning_preference: v as LearningPreference }))}
                            />
                        )}
                        {step === 2 && (
                            <Step2Domain
                                direction={direction}
                                value={answers.domain_interest}
                                onChange={v => setAnswers(a => ({ ...a, domain_interest: v }))}
                            />
                        )}
                        {step === 3 && (
                            <Step3Persona
                                value={answers.mentor_persona}
                                onChange={v => setAnswers(a => ({ ...a, mentor_persona: v as MentorPersona }))}
                            />
                        )}
                        {step === 4 && (
                            <Step4Goal
                                value={answers.career_goal}
                                onChange={v => setAnswers(a => ({ ...a, career_goal: v as CareerGoal }))}
                            />
                        )}
                    </div>

                    {}
                    <div className="px-7 pb-7 flex items-center justify-between gap-3">
                        <button
                            onClick={handleBack}
                            disabled={step === 1}
                            className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors duration-150 rounded-xl hover:bg-slate-50"
                        >
                            <IconArrowLeft className="w-4 h-4" />
                            Назад
                        </button>

                        <button
                            onClick={handleNext}
                            disabled={!canProceed() || loading}
                            className={`
                                flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold
                                transition-all duration-150 active:scale-95
                                ${canProceed() && !loading
                                    ? "bg-violet-600 hover:bg-orange-500 text-white shadow-md shadow-violet-200"
                                    : "bg-slate-100 text-slate-400 cursor-not-allowed"
                                }
                            `}
                        >
                            {loading ? (
                                <>
                                    <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                                    Создаём курс...
                                </>
                            ) : step === 4 ? (
                                "Начать обучение"
                            ) : (
                                <>
                                    Далее
                                    <IconArrowRight className="w-4 h-4" />
                                </>
                            )}
                        </button>
                    </div>
                </SurfaceCard>

                {}
                <p className="text-center text-xs text-slate-300 dark:text-gray-600 mt-5">
                    Настройки можно изменить в профиле курса после создания
                </p>
            </div>
            </div>
        </AppLayout>
    );
}
