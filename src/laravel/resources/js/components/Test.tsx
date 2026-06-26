import React, { useState, useEffect, useCallback, useRef } from "react";
import axios from "axios";
import { ThemedMonacoEditor } from "@/components/ThemedMonacoEditor";
import {
    loadAnyStoredTest,
    clearStoredTest,
    patchStoredTest,
    saveStoredTest,
    markAwaitingFirstQuestion,
    markFirstQuestionReadySeen,
    persistResultsScreen,
    StoredAdaptiveTest,
    StoredCodeFeedback,
} from "@/lib/adaptiveTestStorage";
import { GENERATING_STATUSES } from "@/lib/testSessionPoll";
import {
    startTestSessionPoll,
    stopTestSessionPoll,
} from "@/lib/testSessionPoll";
import {
    SENIOR_PREVIEW_EVENT,
    activateSeniorPreview,
    consumeSeniorPreviewPending,
    type SeniorPreviewResult,
} from "@/lib/seniorPreview";
import { LoadingDots } from "@/components/LoadingDots";
import { AnswerCheckbox } from "@/components/AnswerCheckbox";
import { SurfaceCard } from "@/components/ui/SurfaceCard";


interface Question {
    id: number;
    type: "single" | "multiple";
    topic: string;
    difficulty: string;
    text: string;
    options: string[];
    correct: number[];
}

const TEST_SPINNER_CLASS =
    "h-10 w-10 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600 dark:border-violet-900/70 dark:border-t-violet-400";

const TEST_LOADING_SHELL_CLASS =
    "flex min-h-[calc(100vh-220px)] w-full max-w-md flex-col items-center justify-center gap-4 px-4 text-center text-slate-500 dark:text-gray-400";

function normalizeQuestion(raw: Record<string, unknown>): Question {
    const correct = Array.isArray(raw.correct)
        ? raw.correct.map((v) => Number(v)).filter((n) => Number.isFinite(n))
        : [0];
    const options = Array.isArray(raw.options) ? (raw.options as string[]) : [];
    const text = String(raw.text ?? "");

    let type: Question["type"] = raw.type === "multiple" ? "multiple" : "single";
    if (
        type !== "multiple"
        && (correct.length > 1
            || options.length >= 5
            || /выберите все подходящ/i.test(text))
    ) {
        type = "multiple";
    }

    return {
        id: Number(raw.id),
        type,
        topic: String(raw.topic ?? ""),
        difficulty: String(raw.difficulty ?? ""),
        text,
        options,
        correct: correct.length > 0 ? correct : [0],
    };
}

interface TestResult {
    overall_level: string;
    description: string;
    competencies: Record<string, number>;
    weak_topics: string[];
    strong_topics: string[];
    recommendation: string;
    code_feedback: string;
}

interface CodeFeedback {
    summary: string;
    strengths: string[];
    improvements: string[];
    score: number;
}


interface TestProps {
    direction: string;
    initialSessionId?: string;
    forceFresh?: boolean;
    onContinue?: (overallLevel: string) => void;
}

const LEVEL_CONFIG: Record<string, {
    label: string;
    badge: string;
    pill: string;
    bar: string;
}> = {
    determining: {
        label: "ОПРЕДЕЛЯЕТСЯ...",
        badge: "bg-violet-100 text-violet-500 border border-violet-200",
        pill:  "bg-violet-50 text-violet-600 border-2 border-violet-300",
        bar:   "bg-violet-300",
    },
    beginner: {
        label: "BEGINNER",
        badge: "bg-slate-100 text-slate-500 border border-slate-200",
        pill:  "bg-slate-100 text-slate-600 border-2 border-slate-300",
        bar:   "bg-slate-400",
    },
    junior: {
        label: "JUNIOR",
        badge: "bg-violet-100 text-violet-700 border border-violet-300",
        pill:  "bg-violet-100 text-violet-800 border-2 border-violet-400",
        bar:   "bg-violet-500",
    },
    middle: {
        label: "MIDDLE",
        badge: "bg-purple-100 text-purple-700 border border-purple-300",
        pill:  "bg-purple-100 text-purple-800 border-2 border-purple-400",
        bar:   "bg-purple-500",
    },
    senior: {
        label: "SENIOR",
        badge: "bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-300",
        pill:  "bg-fuchsia-100 text-fuchsia-800 border-2 border-fuchsia-400",
        bar:   "bg-fuchsia-500",
    },
};


function formatStreakLabel(count: number): string {
    if (count === 0) return "0 правильных ответов подряд";
    const word =
        count === 1 ? "правильный ответ" : count < 5 ? "правильных ответа" : "правильных ответов";
    return `${count} ${word} подряд`;
}

function LevelDeterminingLabel() {
    return (
        <span
            className="inline-flex items-center text-violet-600"
            aria-live="polite"
            aria-busy="true"
            aria-label="Уровень определяется"
        >
            <LoadingDots className="ml-0" />
        </span>
    );
}

interface ResultsScreenProps {
    result: TestResult;
    codeFeedback: CodeFeedback | null;
    onRestart: () => void;
    onContinue?: (overallLevel: string) => void;
}

const LEVEL_TITLE_RU: Record<string, string> = {
    beginner: "Начинающий",
    junior: "Junior",
    middle: "Middle",
    senior: "Senior",
    determining: "Определяется",
};

function CompetencyStatus({ mastered }: { mastered: boolean }) {
    return (
        <span className="inline-flex items-center gap-2 shrink-0">
            <AnswerCheckbox checked={mastered} />
            <span className={`text-xs font-medium ${mastered ? "text-violet-700" : "text-slate-500"}`}>
                {mastered ? "Освоено" : "Не освоено"}
            </span>
        </span>
    );
}

function CodeScoreRing({ score }: { score: number }) {
    const pct = Math.min(100, Math.max(0, score));
    const radius = 34;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (pct / 100) * circumference;
    const ringClass =
        score >= 70 ? "stroke-emerald-500" : score >= 50 ? "stroke-amber-500" : "stroke-rose-500";
    const textClass =
        score >= 70 ? "text-emerald-700" : score >= 50 ? "text-amber-700" : "text-rose-700";

    return (
        <div className="relative h-[88px] w-[88px] shrink-0">
            <svg className="h-full w-full -rotate-90" viewBox="0 0 80 80" aria-hidden>
                <circle cx="40" cy="40" r={radius} fill="none" className="stroke-slate-200" strokeWidth="6" />
                <circle
                    cx="40"
                    cy="40"
                    r={radius}
                    fill="none"
                    className={ringClass}
                    strokeWidth="6"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className={`text-xl font-bold tabular-nums leading-none ${textClass}`}>{score}</span>
                <span className="mt-0.5 text-[10px] font-medium text-slate-400">из 100</span>
            </div>
        </div>
    );
}

const ResultsScreen: React.FC<ResultsScreenProps> = ({ result, codeFeedback, onRestart, onContinue }) => {
    const cfg = LEVEL_CONFIG[result.overall_level] ?? LEVEL_CONFIG.junior;
    const levelTitle = LEVEL_TITLE_RU[result.overall_level] ?? result.overall_level;

    return (
        <SurfaceCard className="w-full max-w-2xl mx-auto pb-6" innerClassName="p-6 flex flex-col gap-5">
            <div className="flex items-start justify-between gap-6 pb-5 border-b border-violet-100 dark:border-violet-900/40">
                <div className="min-w-0">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-violet-600 dark:text-violet-400">
                        Результат теста
                    </p>
                    <p className="text-sm text-slate-700 dark:text-gray-300 mt-2 leading-relaxed">{result.description}</p>
                </div>
                <div className="shrink-0 text-right pl-2">
                    <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400 mb-1">
                        Уровень
                    </p>
                    <p className="text-base font-bold tracking-tight text-violet-600">
                        {cfg.label}
                    </p>
                    <p className="text-xs text-slate-500 mt-0.5">{levelTitle}</p>
                </div>
            </div>

            <div className="rounded-lg border border-violet-100 dark:border-violet-900/40 bg-violet-50/40 dark:bg-violet-950/30 px-4 py-3 border-l-[3px] border-l-violet-500">
                <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-600 dark:text-violet-400 mb-1.5">
                    Рекомендация
                </p>
                <p className="text-sm text-slate-700 dark:text-gray-300 leading-relaxed">{result.recommendation}</p>
            </div>

            {Object.keys(result.competencies).length > 0 && (
                <div className="rounded-lg border border-violet-100 dark:border-violet-900/40 overflow-hidden bg-white dark:bg-gray-900">
                    <div className="px-4 py-2.5 border-b border-violet-50 dark:border-violet-900/30 bg-violet-50/30 dark:bg-violet-950/20">
                        <h3 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-600">
                            Компетенции
                        </h3>
                    </div>
                    <ul className="divide-y divide-violet-50">
                        {Object.entries(result.competencies).map(([name, score]) => (
                            <li
                                key={name}
                                className="px-4 py-2.5 flex items-center justify-between gap-4"
                            >
                                <span className="text-sm text-slate-800 flex-1 min-w-0">{name}</span>
                                <CompetencyStatus mastered={score === 100} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {(result.strong_topics.length > 0 || result.weak_topics.length > 0) && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {result.strong_topics.length > 0 && (
                        <div className="rounded-lg border border-violet-100 bg-violet-50/25 px-4 py-3">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-600 mb-2">
                                Сильные стороны
                            </p>
                            <ul className="space-y-1.5">
                                {result.strong_topics.map((t) => (
                                    <li key={t} className="text-sm text-slate-700 leading-snug flex gap-2">
                                        <span className="text-violet-500 shrink-0">·</span>
                                        <span>{t}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {result.weak_topics.length > 0 && (
                        <div className="rounded-lg border border-slate-200 px-4 py-3">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-600 mb-2">
                                Нужно подтянуть
                            </p>
                            <ul className="space-y-1.5">
                                {result.weak_topics.map((t) => (
                                    <li key={t} className="text-sm text-slate-600 leading-snug flex gap-2">
                                        <span className="text-violet-400 shrink-0">·</span>
                                        <span>{t}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {codeFeedback && (
                <div className="rounded-lg border border-violet-100 px-4 py-4 space-y-4 bg-white">
                    <div className="flex items-start gap-4">
                        <CodeScoreRing score={codeFeedback.score} />
                        <div className="min-w-0 flex-1 pt-1">
                            <h3 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-600">
                                Практическая задача
                            </h3>
                            <p className="mt-2 text-sm text-slate-600 leading-relaxed">{codeFeedback.summary}</p>
                        </div>
                    </div>
                    {(codeFeedback.strengths?.length > 0 || codeFeedback.improvements?.length > 0) && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-violet-50">
                            {codeFeedback.strengths?.length > 0 && (
                                <div>
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-600 mb-2">
                                        Сильные стороны
                                    </p>
                                    <ul className="space-y-2">
                                        {codeFeedback.strengths.map((s, i) => (
                                            <li key={i} className="flex gap-2.5 text-sm text-slate-700 leading-snug">
                                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">
                                                    +
                                                </span>
                                                <span>{s}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {codeFeedback.improvements?.length > 0 && (
                                <div>
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-rose-600 mb-2">
                                        Что улучшить
                                    </p>
                                    <ul className="space-y-2">
                                        {codeFeedback.improvements.map((s, i) => (
                                            <li key={i} className="flex gap-2.5 text-sm text-slate-600 leading-snug">
                                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-700">
                                                    −
                                                </span>
                                                <span>{s}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            <div className="flex flex-col sm:flex-row gap-2.5 pt-1">
                {onContinue && (
                    <button
                        type="button"
                        onClick={() => onContinue(result.overall_level)}
                        className="flex-1 py-2.5 bg-violet-600 hover:bg-orange-500 text-white text-sm font-semibold rounded-lg transition-colors duration-150"
                    >
                        Продолжить
                    </button>
                )}
                <button
                    type="button"
                    onClick={onRestart}
                    className={`flex-1 py-2.5 text-sm font-semibold rounded-lg border transition-colors duration-150 ${
                        onContinue
                            ? "border-violet-200 text-violet-700 hover:bg-violet-50"
                            : "bg-violet-600 hover:bg-orange-500 text-white border-transparent"
                    }`}
                >
                    Пройти тест заново
                </button>
            </div>
        </SurfaceCard>
    );
};


export const Test: React.FC<TestProps> = ({ direction, initialSessionId, forceFresh = false, onContinue }) => {
    const [sessionId, setSessionId]               = useState<string | null>(null);
    const [assessmentUuid, setAssessmentUuid]     = useState<string | null>(null);
    const [currentQuestion, setCurrentQuestion]   = useState<Question | null>(null);
    const [questionNumber, setQuestionNumber]     = useState(0);

    const [selectedAnswers, setSelectedAnswers]   = useState<number | number[] | null>(null);
    const [showFeedback, setShowFeedback]         = useState(false);
    const [lastAnswerCorrect, setLastAnswerCorrect] = useState<boolean | null>(null);
    const [streak, setStreak]                     = useState(0);
    const [displayLevel, setDisplayLevel]         = useState<string>("determining");

    const [loading, setLoading]                   = useState(false);
    const [generatingStart, setGeneratingStart]   = useState(false);
    const [advancingNext, setAdvancingNext]       = useState(false);
    const [generatingTask, setGeneratingTask]     = useState(false);

    const [showTask, setShowTask]                 = useState(false);
    const [taskData, setTaskData]                 = useState<any>(null);
    const [taskCode, setTaskCode]                 = useState("");
    const [taskLoading, setTaskLoading]           = useState(false);

    const [testResult, setTestResult]             = useState<TestResult | null>(null);
    const [codeFeedback, setCodeFeedback]         = useState<CodeFeedback | null>(null);
    const [showResults, setShowResults]           = useState(false);
    const [finishStep, setFinishStep]             = useState<"task" | "results" | null>(null);

    const pollHandleRef = useRef<{ cancelled: boolean } | null>(null);

    const persistDraft = useCallback((sid: string, patch: {
        selectedAnswers?: number | number[] | null;
        questionId?: number | null;
        showFeedback?: boolean;
    }) => {
        const prev = loadAnyStoredTest();
        saveStoredTest({
            sessionId: sid,
            direction,
            selectedAnswers: patch.selectedAnswers ?? selectedAnswers,
            questionId: patch.questionId ?? currentQuestion?.id ?? null,
            showFeedback: patch.showFeedback ?? showFeedback,
            notifiedReady: prev?.notifiedReady ?? false,
            generatingFirstQuestion: prev?.generatingFirstQuestion ?? false,
        });
    }, [direction, selectedAnswers, currentQuestion?.id, showFeedback]);

    const applyQuestionFromPayload = useCallback((data: Record<string, unknown>, stored?: StoredAdaptiveTest | null) => {
        if (!data.question) return;

        setCurrentQuestion(normalizeQuestion(data.question as Record<string, unknown>));
        setQuestionNumber(Number(data.question_number ?? 1));
        const fb = Boolean(data.show_feedback);
        setShowFeedback(fb);

        if (data.last_is_correct !== undefined && data.last_is_correct !== null) {
            setLastAnswerCorrect(Boolean(data.last_is_correct));
        } else if (!fb) {
            setLastAnswerCorrect(null);
        }

        const fromServer = data.last_submitted_answer;
        if (fromServer !== undefined && fromServer !== null) {
            setSelectedAnswers(fromServer as number | number[]);
        } else if (
            stored?.selectedAnswers !== undefined
            && stored.selectedAnswers !== null
            && (fb || stored.showFeedback)
        ) {
            setSelectedAnswers(stored.selectedAnswers);
        } else if (
            !fb
            && stored?.selectedAnswers !== undefined
            && stored.selectedAnswers !== null
            && stored.questionId === Number((data.question as Record<string, unknown>)?.id)
        ) {
            setSelectedAnswers(stored.selectedAnswers);
        } else if (!fb) {
            const q = normalizeQuestion(data.question as Record<string, unknown>);
            setSelectedAnswers(q.type === "multiple" ? [] : null);
        }
    }, []);

    const applySessionPayload = useCallback((data: Record<string, unknown>, stored?: StoredAdaptiveTest | null) => {
        if (data.error) return false;

        if (data.session_id) setSessionId(String(data.session_id));

        if (data.is_finished && data.result) {
            setTestResult(data.result as TestResult);
            if (data.assessment_uuid) setAssessmentUuid(String(data.assessment_uuid));

            const task = data.practical_task as { title?: string; starter_code?: string } | undefined;
            const taskReady = Boolean(task?.title);
            const screen = stored?.screen;
            const apiStatus = String(data.status ?? "");

            if (stored?.codeFeedback) {
                setCodeFeedback(stored.codeFeedback as CodeFeedback);
            }

            if (apiStatus === "generating_task" && !taskReady) {
                setGeneratingTask(true);
                setGeneratingStart(false);
                setShowTask(false);
                setShowResults(false);
                applyQuestionFromPayload(data, stored);
                setFinishStep(null);
                return true;
            }

            if (screen === "results") {
                setShowResults(true);
                setShowTask(false);
                setCurrentQuestion(null);
                setFinishStep(null);
                if (data.session_id) {
                    persistResultsScreen(
                        String(data.session_id),
                        stored?.direction ?? String(data.direction ?? direction),
                        stored?.codeFeedback ?? null,
                    );
                }
                return true;
            }

            setGeneratingTask(false);
            setShowResults(false);

            if (taskReady && task && screen === "task") {
                setTaskData(task);
                setTaskCode(task.starter_code || "");
                setShowTask(true);
                setShowFeedback(false);
                setCurrentQuestion(null);
                setFinishStep(null);
                return true;
            }

            if (taskReady && task) {
                setTaskData(task);
                setTaskCode(task.starter_code || "");
                setShowTask(false);
                applyQuestionFromPayload(data, stored);
                setFinishStep("task");
                return true;
            }

            setShowResults(true);
            setShowTask(false);
            setCurrentQuestion(null);
            setFinishStep(null);
            if (data.session_id) {
                persistResultsScreen(
                    String(data.session_id),
                    stored?.direction ?? String(data.direction ?? direction),
                    stored?.codeFeedback ?? null,
                );
            }
            return true;
        }

        if (data.display_level) setDisplayLevel(String(data.display_level));
        if (data.current_streak !== undefined) setStreak(Number(data.current_streak));

        if (data.status === "generating_start") {
            setGeneratingStart(true);
            setGeneratingTask(false);
            setCurrentQuestion(null);
            setShowTask(false);
            return true;
        }

        setGeneratingStart(false);

        if (data.status === "generating_next") {
            setAdvancingNext(!data.question);
            if (data.question) {
                applyQuestionFromPayload(data, stored);
            }
            return true;
        }

        setAdvancingNext(false);

        if (data.question) {
            applyQuestionFromPayload(data, stored);
            if (
                typeof window !== "undefined"
                && window.location.pathname.startsWith("/test")
            ) {
                markFirstQuestionReadySeen();
            }
            return true;
        }

        return false;
    }, [applyQuestionFromPayload, direction]);

    const pollSession = useCallback((sid: string, onReady?: () => void) => {
        const stored = loadAnyStoredTest();
        startTestSessionPoll(sid, pollHandleRef, {
            onTick: (data) => applySessionPayload(data as Record<string, unknown>, stored),
            onDone: (data) => {
                applySessionPayload(data as Record<string, unknown>, loadAnyStoredTest());
                onReady?.();
            },
            onMissing: () => clearStoredTest(),
        });
    }, [applySessionPayload]);

    useEffect(() => {
        return () => stopTestSessionPoll(pollHandleRef);
    }, []);

    useEffect(() => {
        const onSeniorPreview = (event: Event) => {
            const detail = (event as CustomEvent<{ result: SeniorPreviewResult; direction: string }>).detail;
            if (!detail?.result) return;

            stopTestSessionPoll(pollHandleRef);
            setLoading(false);
            setGeneratingStart(false);
            setGeneratingTask(false);
            setShowTask(false);
            setCurrentQuestion(null);
            setFinishStep(null);
            setTestResult(detail.result as TestResult);
            setCodeFeedback(null);
            setShowResults(true);
            setDisplayLevel("senior");
            setStreak(5);

            const sid = sessionId ?? loadAnyStoredTest()?.sessionId ?? initialSessionId ?? "preview";
            setSessionId(sid);
            persistResultsScreen(sid, detail.direction ?? direction, null);
        };

        window.addEventListener(SENIOR_PREVIEW_EVENT, onSeniorPreview);
        return () => window.removeEventListener(SENIOR_PREVIEW_EVENT, onSeniorPreview);
    }, [sessionId, direction, initialSessionId]);

    useEffect(() => {
        if (!showResults || !testResult || !sessionId) return;
        stopTestSessionPoll(pollHandleRef);
        persistResultsScreen(sessionId, direction, codeFeedback as StoredCodeFeedback | null);
    }, [showResults, testResult, sessionId, direction, codeFeedback]);

    useEffect(() => {
        if (!sessionId || showResults) return;
        const prev = loadAnyStoredTest();
        saveStoredTest({
            sessionId,
            direction,
            selectedAnswers,
            questionId: currentQuestion?.id ?? null,
            showFeedback,
            screen: showTask ? "task" : "question",
            finishStep,
            notifiedReady: prev?.sessionId === sessionId ? (prev.notifiedReady ?? false) : false,
            generatingFirstQuestion:
                prev?.sessionId === sessionId ? (prev.generatingFirstQuestion ?? false) : generatingStart,
            codeFeedback: prev?.sessionId === sessionId ? (prev.codeFeedback ?? null) : null,
        });
    }, [sessionId, direction, currentQuestion?.id, selectedAnswers, showFeedback, showTask, finishStep, showResults, generatingStart]);

    const resumeOrStart = useCallback(async (isRetake = false) => {
        setLoading(true);
        setShowTask(false);
        setGeneratingTask(false);
        setGeneratingStart(false);

        if (isRetake || forceFresh) {
            clearStoredTest();
            if (isRetake) {
                await axios.post("/test/prepare-retake");
            }
        }

        let stored = isRetake || forceFresh ? null : loadAnyStoredTest();
        if (stored && stored.direction.toLowerCase() !== direction.toLowerCase()) {
            clearStoredTest();
            stored = null;
        }

        const restoreResults = Boolean(stored?.screen === "results");
        if (!restoreResults) {
            setShowResults(false);
            setCodeFeedback(null);
        } else if (stored?.codeFeedback) {
            setCodeFeedback(stored.codeFeedback as CodeFeedback);
        }

        let sid = isRetake || forceFresh
            ? null
            : (initialSessionId ?? stored?.sessionId ?? null);

        if (sid) {
            try {
                const res = await axios.get(`/test/session/${sid}`);
                if (!res.data.error && res.data.status !== "missing") {
                    setSessionId(sid);
                    applySessionPayload(res.data, stored);

                    const needsPoll = GENERATING_STATUSES.has(res.data.status)
                        || (res.data.is_finished && res.data.generating_task && !res.data.practical_task?.title);

                    if (needsPoll) {
                        if (res.data.status === "generating_start") {
                            setGeneratingStart(true);
                            markAwaitingFirstQuestion();
                        }
                        if (res.data.status === "generating_task" || res.data.generating_task) setGeneratingTask(true);
                        pollSession(sid, () => {
                            setGeneratingStart(false);
                            setAdvancingNext(false);
                            setGeneratingTask(false);
                        });
                    }
                    setLoading(false);
                    return;
                }
            } catch {
                
            }
            clearStoredTest();
            sid = null;
        }

        try {
            const res = await axios.post("/test/start", { direction });
            if (res.data.error) {
                alert(res.data.message);
                return;
            }
            const newId = res.data.session_id as string;
            setSessionId(newId);
            saveStoredTest({
                sessionId: newId,
                direction,
                selectedAnswers: null,
                questionId: null,
                showFeedback: false,
                notifiedReady: false,
                generatingFirstQuestion: res.data.status === "generating_start",
            });

            if (typeof window !== "undefined" && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set("session", newId);
                url.searchParams.delete("fresh");
                window.history.replaceState({}, "", url.toString());
            }

            if (res.data.status === "generating_start") {
                setGeneratingStart(true);
                markAwaitingFirstQuestion();
                pollSession(newId, () => setGeneratingStart(false));
            } else {
                applySessionPayload(res.data);
            }
        } catch {
            alert("Ошибка запуска теста");
        } finally {
            setLoading(false);
        }
    }, [direction, initialSessionId, forceFresh, applySessionPayload, pollSession]);

    useEffect(() => {
        const boot = async () => {
            if (consumeSeniorPreviewPending()) {
                setLoading(true);
                const ok = await activateSeniorPreview();
                setLoading(false);
                if (ok) return;
            }
            await resumeOrStart(false);
        };
        void boot();
    }, []);

    useEffect(() => {
        if (
            !sessionId
            || loading
            || generatingStart
            || generatingTask
            || advancingNext
            || showFeedback
            || currentQuestion
            || showTask
            || showResults
        ) {
            return;
        }
        pollSession(sessionId, () => {
            setGeneratingStart(false);
            setAdvancingNext(false);
            setGeneratingTask(false);
        });
        return () => stopTestSessionPoll(pollHandleRef);
    }, [
        sessionId,
        loading,
        generatingStart,
        generatingTask,
        advancingNext,
        showFeedback,
        currentQuestion,
        showTask,
        showResults,
        pollSession,
    ]);

    const startTest = (isRetake = false) => resumeOrStart(isRetake);

    const submitAnswerWith = async (answerToSubmit: number | number[]) => {
        if (!sessionId || !currentQuestion) return;
        setLoading(true);
        try {
            const res = await axios.post("/test/submit-answer", {
                session_id:  sessionId,
                question_id: currentQuestion.id,
                answer:      answerToSubmit,
            });

            if (res.data.error) {
                alert(res.data.message ?? "Ошибка при проверке ответа");
                return;
            }

            setShowFeedback(true);
            setLastAnswerCorrect(res.data.is_correct ?? null);
            if (res.data.current_streak !== undefined) setStreak(res.data.current_streak);
            if (res.data.display_level) setDisplayLevel(res.data.display_level);
            persistDraft(sessionId, { selectedAnswers: answerToSubmit, showFeedback: true });

            if (res.data.is_finished) {
                setTestResult(res.data.result);
                if (res.data.assessment_uuid) setAssessmentUuid(res.data.assessment_uuid);

                if (res.data.generating_task) {
                    setGeneratingTask(true);
                    pollSession(sessionId, () => setGeneratingTask(false));
                    return;
                }

                if (res.data.practical_task?.title) {
                    setTaskData(res.data.practical_task);
                    setTaskCode(res.data.practical_task.starter_code || "");
                    setFinishStep("task");
                } else {
                    setFinishStep("results");
                }
            } else if (res.data.generating_next) {
                pollSession(sessionId);
            }
        } catch (err) {
            console.error(err);
            alert("Ошибка при отправке ответа");
        } finally {
            setLoading(false);
        }
    };

    const submitAnswer = () => {
        if (selectedAnswers === null) return;
        if (
            currentQuestion?.type === "multiple"
            && Array.isArray(selectedAnswers)
            && selectedAnswers.length === 0
        ) {
            return;
        }
        submitAnswerWith(selectedAnswers);
    };

 const handleDontKnow = () => {
    if (showFeedback || loading) return;
    submitAnswerWith(-1);
};

    const goToNextStep = async () => {
        if (finishStep === "task") {
            setFinishStep(null);
            setShowFeedback(false);
            setShowTask(true);
            if (sessionId) {
                patchStoredTest({ screen: "task" });
            }
            return;
        }
        if (finishStep === "results") {
            setFinishStep(null);
            setShowFeedback(false);
            setCurrentQuestion(null);
            setShowResults(true);
            if (sessionId) {
                persistResultsScreen(sessionId, direction, codeFeedback as StoredCodeFeedback | null);
            }
            return;
        }

        if (generatingTask && testResult && !finishStep && sessionId) {
            setAdvancingNext(true);
            try {
                await new Promise<void>((resolve) => {
                    const timeout = window.setTimeout(resolve, 120_000);
                    startTestSessionPoll(sessionId, pollHandleRef, {
                        onTick: (data) => applySessionPayload(data as Record<string, unknown>),
                        onDone: (data) => {
                            applySessionPayload(data as Record<string, unknown>);
                            const task = data.practical_task as { title?: string } | undefined;
                            if (task?.title) {
                                setShowFeedback(false);
                                setShowTask(true);
                            }
                            window.clearTimeout(timeout);
                            resolve();
                        },
                    });
                });
            } finally {
                setAdvancingNext(false);
            }
            return;
        }

        if (!sessionId) return;

        setAdvancingNext(true);
        try {
            const tryAdvance = async (): Promise<boolean> => {
                const res = await axios.post("/test/advance", { session_id: sessionId });
                if (res.data.error) {
                    alert(res.data.message);
                    return false;
                }
                if (res.data.generating_next) return false;
                if (res.data.question) {
                    const question = normalizeQuestion(res.data.question as Record<string, unknown>);
                    setCurrentQuestion(question);
                    setQuestionNumber(res.data.question_number);
                    setShowFeedback(false);
                    setSelectedAnswers(question.type === "multiple" ? [] : null);
                    setLastAnswerCorrect(null);
                    persistDraft(sessionId, { selectedAnswers: null, showFeedback: false, questionId: question.id });
                    return true;
                }
                return false;
            };

            if (await tryAdvance()) return;

            await new Promise<void>((resolve) => {
                const timeout = window.setTimeout(resolve, 90_000);
                const stored = loadAnyStoredTest();
                startTestSessionPoll(sessionId, pollHandleRef, {
                    onTick: (data) => applySessionPayload(data as Record<string, unknown>, stored),
                    onDone: async (data) => {
                        applySessionPayload(data as Record<string, unknown>, loadAnyStoredTest());
                        await tryAdvance();
                        window.clearTimeout(timeout);
                        resolve();
                    },
                });
            });
        } finally {
            setAdvancingNext(false);
        }
    };

    const submitTask = async (skipped = false) => {
        if (!assessmentUuid) {
            setShowTask(false);
            setShowResults(true);
            if (sessionId) {
                persistResultsScreen(sessionId, direction, codeFeedback as StoredCodeFeedback | null);
            }
            return;
        }
        setTaskLoading(true);
        let feedback: StoredCodeFeedback | null = codeFeedback as StoredCodeFeedback | null;
        try {
            const res = await axios.post("/test/complete-task", {
                assessment_uuid: assessmentUuid,
                code:            skipped ? null : taskCode,
                skipped,
            });
            if (res.data.code_feedback) {
                setCodeFeedback(res.data.code_feedback);
                feedback = res.data.code_feedback as StoredCodeFeedback;
            }
        } catch (err) {
            console.error(err);
        } finally {
            setTaskLoading(false);
            setShowTask(false);
            setShowResults(true);
            stopTestSessionPoll(pollHandleRef);
            if (sessionId) {
                persistResultsScreen(sessionId, direction, feedback);
            }
        }
    };

    const getOptionClasses = (idx: number, isSelected: boolean): string => {
        const base = "flex items-center gap-3 w-full px-4 py-3 rounded-xl border-2 text-left transition-all duration-150 cursor-pointer select-none";
        if (!showFeedback) {
            return isSelected
                ? `${base} border-violet-500 bg-violet-50 dark:bg-violet-950/40 text-violet-900 dark:text-violet-100`
                : `${base} border-slate-300 dark:border-gray-700 surface-control hover:border-violet-200 dark:hover:border-violet-700 hover:bg-violet-50/40 dark:hover:bg-violet-950/20 text-slate-800 dark:text-gray-200`;
        }
        const isCorrect = currentQuestion?.correct.includes(idx) ?? false;
        if (isCorrect) {
            return `${base} border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-100 cursor-default`;
        }
        if (isSelected && !isCorrect) {
            return `${base} border-red-400 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-200 cursor-default`;
        }
        return `${base} border-slate-200 dark:border-gray-700 bg-slate-50/80 dark:bg-gray-800/60 text-slate-500 dark:text-gray-400 cursor-default`;
    };

    const streakPct     = Math.min((streak / 5) * 100, 100);
    const streakBarCls  = "h-full rounded-full bg-violet-500 transition-all duration-500";
    const streakLabelCls = "text-xs font-semibold mt-1.5 text-right text-violet-500 dark:text-violet-400";

    if ((loading && !currentQuestion && !showTask && !showResults) || generatingStart) {
        return (
            <div className={TEST_LOADING_SHELL_CLASS}>
                <div className={TEST_SPINNER_CLASS} aria-hidden />
                <span className="text-base font-semibold text-slate-700 dark:text-gray-200">
                    Генерируем первый вопрос
                </span>
                <p className="text-sm leading-relaxed text-slate-500 dark:text-gray-400">
                    Можете перейти в другой раздел — внизу справа появится уведомление, когда вопрос будет готов.
                </p>
            </div>
        );
    }

    if (showResults && testResult) {
        return (
            <ResultsScreen
                result={testResult}
                codeFeedback={codeFeedback}
                onRestart={() => startTest(true)}
                onContinue={onContinue}
            />
        );
    }

    if (showTask && taskData) {
        const lvlCfg = LEVEL_CONFIG[testResult?.overall_level ?? "junior"] ?? LEVEL_CONFIG.junior;
        return (
            <div className="min-h-screen flex items-center justify-center px-16 py-10">
            <div className="w-full max-w-4xl flex flex-col gap-8">
                <div className="flex items-center justify-between">
                    <h2 className="text-3xl font-bold text-slate-900 dark:text-gray-100">Практическая задача</h2>
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-slate-500 dark:text-gray-400 uppercase tracking-wide">Текущий уровень</span>
                        <span className={`text-sm font-bold px-4 py-2 rounded-full tracking-widest ${lvlCfg.badge}`}>
                            {lvlCfg.label}
                        </span>
                    </div>
                </div>

                <SurfaceCard innerClassName="px-8 py-6 space-y-4">
                    <h3 className="font-bold text-slate-900 dark:text-gray-100 text-xl">{taskData.title}</h3>
                    {taskData.action && (
                        <p className="text-slate-900 dark:text-gray-100 text-base leading-relaxed font-medium">{taskData.action}</p>
                    )}
                    {taskData.function_signature && (
                        <pre className="text-sm font-mono text-slate-900 dark:text-gray-100 border-2 border-violet-500 dark:border-violet-500 rounded-lg px-4 py-3 overflow-x-auto bg-white dark:bg-gray-950">
                            {taskData.function_signature}
                        </pre>
                    )}
                    {taskData.description && (
                        <p className="text-slate-600 dark:text-gray-300 text-base leading-relaxed">{taskData.description}</p>
                    )}

                    {taskData.constraints?.length > 0 && (
                        <div className="bg-violet-50 dark:bg-violet-950/30 border border-violet-200 dark:border-violet-800 rounded-lg px-5 py-4">
                            <p className="text-xs font-bold text-violet-700 dark:text-violet-300 mb-2 uppercase tracking-widest">Ограничения</p>
                            <ul className="space-y-1.5">
                                {taskData.constraints.map((c: string, i: number) => (
                                    <li key={i} className="text-base text-violet-700 dark:text-violet-200 flex gap-2">
                                        <span className="shrink-0">—</span>{c}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                </SurfaceCard>

                <SurfaceCard innerClassName="overflow-hidden border-2 border-violet-500 dark:border-violet-600 bg-white dark:bg-black">
                    <ThemedMonacoEditor
                        height="400px"
                        defaultLanguage={direction.toLowerCase()}
                        value={taskCode}
                        onChange={(v) => setTaskCode(v || "")}
                        options={{ minimap: { enabled: false }, fontSize: 15, padding: { top: 14 } }}
                    />
                </SurfaceCard>

                <div className="flex gap-4">
                    <button
                        onClick={() => submitTask(false)}
                        disabled={taskLoading}
                        className="flex-1 py-4 bg-violet-600 hover:bg-orange-500 disabled:bg-slate-200 disabled:text-slate-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500 text-white font-bold rounded-xl transition-colors duration-150 text-base"
                    >
                        {taskLoading ? "Анализ кода..." : "Отправить решение"}
                    </button>
                    <button
                        onClick={() => submitTask(true)}
                        disabled={taskLoading}
                        className="px-10 py-4 bg-white dark:bg-gray-900 border-2 border-slate-200 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-800 text-slate-600 dark:text-gray-300 font-semibold rounded-xl transition-colors duration-150 text-base"
                    >
                        Пропустить
                    </button>
                </div>
            </div>
            </div>
        );
    }

    if (currentQuestion) {
        const lvlCfg = LEVEL_CONFIG[displayLevel] ?? LEVEL_CONFIG.determining;

        return (
            <SurfaceCard className="w-full max-w-3xl mx-auto" innerClassName="p-5 flex flex-col gap-3">
                <div className="flex items-center justify-between bg-slate-50 dark:bg-gray-800/60 border border-slate-200 dark:border-gray-700 rounded-xl px-4 py-2.5">
                    <span className="font-bold text-slate-900 dark:text-gray-100 text-lg">Вопрос {questionNumber}</span>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-medium text-slate-500 dark:text-gray-400 uppercase tracking-wide">Текущий уровень:</span>
                        {displayLevel === "determining" ? (
                            <LevelDeterminingLabel />
                        ) : (
                            <span className={`text-xs font-bold px-3 py-1.5 rounded-full tracking-widest ${lvlCfg.badge}`}>
                                {lvlCfg.label}
                            </span>
                        )}
                    </div>
                </div>

                {showFeedback && lastAnswerCorrect !== null && (
                    <div className={`px-4 py-3 rounded-xl font-semibold text-sm border ${
                        lastAnswerCorrect
                            ? "bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200"
                            : "bg-red-50 dark:bg-red-950/40 border-red-300 dark:border-red-800 text-red-800 dark:text-red-200"
                    }`}>
                        {lastAnswerCorrect ? "Ответ верный" : "Ответ неверный"}
                        {finishStep === "task" && generatingTask && !taskData?.title && (
                            <span className="block mt-1 font-normal text-sm opacity-90">
                                Готовим практическую задачу — нажмите «Далее», когда кнопка станет активной.
                            </span>
                        )}
                    </div>
                )}

                <div>
                    <div className="h-2 bg-slate-100 dark:bg-gray-800 rounded-full overflow-hidden">
                        <div className={streakBarCls} style={{ width: `${streakPct}%` }} />
                    </div>
                    <p className={streakLabelCls}>{formatStreakLabel(streak)}</p>
                </div>

                <div>
                    <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                        {currentQuestion.topic} · {currentQuestion.difficulty}
                    </p>
                    <h3 className="text-xl font-semibold text-slate-900 dark:text-gray-100 leading-snug">
                        {currentQuestion.text}
                    </h3>
                </div>

                <div className="space-y-2.5">
                    {currentQuestion.options.map((opt, idx) => {
                        const isSelected = currentQuestion.type === "single"
                            ? selectedAnswers === idx
                            : Array.isArray(selectedAnswers) && selectedAnswers.includes(idx);

                        const inputProps = currentQuestion.type === "single"
                            ? {
                                type: "radio" as const,
                                name: `q-${currentQuestion.id}`,
                                checked: isSelected,
                                onChange: () => !showFeedback && setSelectedAnswers(idx),
                                disabled: showFeedback,
                              }
                            : {
                                type: "checkbox" as const,
                                checked: isSelected,
                                onChange: (e: React.ChangeEvent<HTMLInputElement>) => {
                                    if (showFeedback) return;
                                    const prev = Array.isArray(selectedAnswers) ? selectedAnswers : [];
                                    setSelectedAnswers(
                                        e.target.checked ? [...prev, idx] : prev.filter(v => v !== idx)
                                    );
                                },
                                disabled: showFeedback,
                              };

                        return (
                            <label key={idx} className={getOptionClasses(idx, isSelected)}>
                                <input
                                    {...inputProps}
                                    className={
                                        currentQuestion.type === "multiple"
                                            ? "sr-only"
                                            : "shrink-0 accent-violet-600 w-5 h-5"
                                    }
                                />
                                {currentQuestion.type === "multiple" && (
                                    <AnswerCheckbox checked={isSelected} />
                                )}
                                <span className="text-base flex-1">{opt}</span>
                            </label>
                        );
                    })}
                </div>

                <div className="flex gap-3 justify-end pt-1">
                    {!showFeedback ? (
                        <>
                            <button
                                onClick={handleDontKnow}
                                disabled={loading}
                                className="px-6 py-2.5 bg-slate-100 dark:bg-gray-800 hover:bg-slate-200 dark:hover:bg-gray-700 text-slate-600 dark:text-gray-300 font-semibold rounded-xl text-sm transition-colors duration-150 disabled:opacity-40"
                            >
                                Не знаю
                            </button>
                            <button
                                onClick={submitAnswer}
                                disabled={
                                    loading
                                    || selectedAnswers === null
                                    || (currentQuestion.type === "multiple"
                                        && Array.isArray(selectedAnswers)
                                        && selectedAnswers.length === 0)
                                }
                                className="px-8 py-2.5 bg-violet-600 hover:bg-orange-500 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition-colors duration-150"
                            >
                                {loading ? "Проверка..." : "Ответить"}
                            </button>
                        </>
                    ) : (
                        <button
                            onClick={goToNextStep}
                            disabled={advancingNext}
                            className="px-8 py-2.5 bg-violet-600 hover:bg-orange-500 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition-colors duration-150 flex items-center gap-2"
                        >
                            {(advancingNext || generatingTask) && (
                                <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                            )}
                            {advancingNext
                                ? "Генерируем следующий вопрос…"
                                : generatingTask
                                    ? "Готовим практическую задачу…"
                                    : finishStep === "task"
                                        ? "Далее к задаче"
                                        : "Далее"}
                        </button>
                    )}
                </div>
            </SurfaceCard>
        );
    }

    if (sessionId) {
        return (
            <div className={TEST_LOADING_SHELL_CLASS}>
                <div className={TEST_SPINNER_CLASS} aria-hidden />
                <span className="text-base font-semibold text-slate-700 dark:text-gray-200">Восстанавливаем тест…</span>
                <button
                    type="button"
                    onClick={() => resumeOrStart(false)}
                    className="text-sm font-medium text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300"
                >
                    Загрузить снова
                </button>
            </div>
        );
    }

    return null;
};
