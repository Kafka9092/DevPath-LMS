import React, { useState, useCallback, useEffect, useRef, useMemo } from "react";
import { Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { AppLayout } from "@/components/Sidebar";
import { Breadcrumbs } from "@/components/Breadcrumbs";
import { CoursePlanDrawer } from "@/components/lesson/CoursePlanDrawer";
import { ExerciseArchivePanel } from "@/components/lesson/ExerciseArchivePanel";
import { LessonStudio, LessonStatus } from "@/components/lesson/LessonStudio";
import { startKafkaJobPoll, stopKafkaJobPoll, waitForKafkaJob } from "@/lib/lessonJobPoll";
import {
    clearLessonWorkspaceForCourse,
    loadLessonWorkspace,
    patchLessonWorkspace,
    rememberWorkspaceContext,
    saveLessonWorkspace,
} from "@/lib/lessonWorkspaceStorage";
import { notifyLessonReady, requestLessonNotificationPermission } from "@/lib/lessonNotifications";
import {
    canRestoreWorkspaceSession,
    clearWorkspaceSession,
    isActiveLessonPhase,
    loadWorkspaceSession,
    saveWorkspaceSession,
} from "@/lib/workspaceSession";
import {
    Course, Subtopic, ExerciseHistoryItem,
    ChatBlock, LessonPhase, MentorActionId, MentorOptionId,
} from "@/types/WorkspaceTypes";
import { useEditorStallMonitor, StallKind } from "@/hooks/useEditorStallMonitor";

interface CatCompetenceRow {
    name: string;
    score: number;
    mastered: boolean;
}

interface InitialCatAssessment {
    overall_level: string;
    direction?: string;
    assessed_at?: string;
    questions_answered?: number;
    competences?: CatCompetenceRow[];
    characteristics?: { name: string; label?: string; score: number }[];
}

interface WorkspaceProps {
    course: Course;
    completed_ids: number[];
    delivery_modes: Record<number, string>;
    first_unlocked: { id: number; title: string; theme_id: number } | null;
    unlock_all_lessons?: boolean;
    exercise_history: ExerciseHistoryItem[];
    initial_cat?: InitialCatAssessment | null;
    [key: string]: unknown;
}

function genId() {
    return typeof crypto !== "undefined"
        ? crypto.randomUUID()
        : Math.random().toString(36).slice(2);
}

function findSubtopicInCourse(course: Course, subtopicId: number): { subtopic: Subtopic; themeTitle: string } | null {
    for (const module of course.modules) {
        for (const theme of module.themes) {
            const found = theme.subtopics.find(s => s.id === subtopicId);
            if (found) {
                return {
                    subtopic: { ...found, theme_id: theme.id },
                    themeTitle: theme.title,
                };
            }
        }
    }
    return null;
}

function pushLessonBlocks(prev: ChatBlock[], raw: ChatBlock): ChatBlock[] {
    const next = [...prev];
    if (raw.mentor_message) {
        next.push({
            id: genId(),
            type: "mentor",
            message: raw.mentor_message,
        });
    }
    const { mentor_message: _m, ...block } = raw;
    next.push({ ...block, id: genId() });
    return next;
}

export default function Workspace() {
    const {
        course,
        completed_ids: initialCompletedIds,
        exercise_history: initialHistory,
        first_unlocked,
        unlock_all_lessons: unlockAllLessons = false,
    } = usePage<WorkspaceProps>().props;

    const [activeSubtopic, setActiveSubtopic] = useState<Subtopic | null>(
        first_unlocked
            ? { id: first_unlocked.id, title: first_unlocked.title, order: 0, theme_id: first_unlocked.theme_id }
            : null
    );
    const [activeThemeTitle, setActiveThemeTitle] = useState("");

    const [completedIds, setCompletedIds] = useState<number[]>(initialCompletedIds);
    const [exerciseHistory, setExerciseHistory] = useState<ExerciseHistoryItem[]>(initialHistory);

    const [phase, setPhase] = useState<LessonPhase>("idle");
    const [blocks, setBlocks] = useState<ChatBlock[]>([]);
    const [loading, setLoading] = useState(false);
    const [nextSubtopic, setNextSubtopic] = useState<{ id: number; title: string } | null>(null);
    const [mentorDeclined, setMentorDeclined] = useState(false);
    const [chatOpenSignal, setChatOpenSignal] = useState(0);
    const [planOpen, setPlanOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [submitLoading, setSubmitLoading] = useState(false);
    const [currentCode, setCurrentCode] = useState("");
    const [lessonStatus, setLessonStatus] = useState<LessonStatus | null>(null);
    const [mentorChatBlocked, setMentorChatBlocked] = useState(false);
    const latestCodeRef = useRef("");
    const restoredRef = useRef(false);
    const contentJobPollRef = useRef<{ cancelled: boolean } | null>(null);
    const generationResumeRef = useRef(false);

    const fetchLessonStatus = useCallback(async (subtopicId: number) => {
        try {
            const res = await axios.post("/workspace/lesson/status", {
                course_id: course.id,
                subtopic_id: subtopicId,
            });
            setLessonStatus(res.data);
            setMentorChatBlocked(Boolean((res.data as LessonStatus).mentor_chat_blocked));
            return res.data as LessonStatus;
        } catch {
            setLessonStatus(null);
            setMentorChatBlocked(false);
            return null;
        }
    }, [course.id]);

    const lessonActive = ["generating", "learning", "theory_review", "task", "evaluating", "completed", "lesson_review"].includes(phase);

    const extractError = (err: unknown): string => {
        if (err instanceof Error && err.message.trim()) {
            return err.message;
        }
        if (axios.isAxiosError(err) && err.response?.data && typeof err.response.data === "object") {
            const data = err.response.data as { error?: string; message?: string };
            return data.error ?? data.message ?? "Ошибка сервера";
        }
        return "Не удалось выполнить запрос";
    };

    const applyStartResponse = useCallback((block: ChatBlock) => {
        setBlocks(pushLessonBlocks([], block));
        if (block.type === "lesson_review") {
            setPhase("lesson_review");
        } else {
            setPhase(block.type === "task" ? "task" : "learning");
            if (block.type === "task") {
                setMentorDeclined(false);
            }
        }
        patchLessonWorkspace({
            generating: false,
            lessonReady: false,
            notifiedReady: true,
            inProgress: true,
            jobId: null,
        });
    }, []);

    const trackBehavior = useCallback(async (eventType: string, metadata: Record<string, unknown> = {}) => {
        if (!activeSubtopic) return null;
        try {
            const res = await axios.post("/workspace/behavior", {
                course_id: course.id,
                subtopic_id: activeSubtopic.id,
                event_type: eventType,
                metadata,
            });
            return res.data as { mentor_block?: ChatBlock | null };
        } catch {
            return null;
        }
    }, [course.id, activeSubtopic]);

    const getTaskBlock = useCallback(
        () => [...blocks].reverse().find(b => b.type === "task") ?? null,
        [blocks],
    );

    const mentorPromptPending = useMemo(() => {
        for (let i = blocks.length - 1; i >= 0; i--) {
            const block = blocks[i];
            if (block.type === "mentor_prompt") return true;
            if (["mentor_menu", "mentor", "hint", "answer"].includes(block.type)) return false;
        }
        return false;
    }, [blocks]);

    const handleStall = useCallback(async (kind: StallKind) => {
        if (!activeSubtopic || phase !== "task") return;

        const data = await trackBehavior(kind, { code_length: latestCodeRef.current.length });
        const block = data?.mentor_block;
        if (!block) return;

        setBlocks(prev => {
            if (prev.some(b => b.type === "mentor_prompt" || b.type === "mentor_menu")) return prev;
            return [...prev, { ...block, id: genId() }];
        });
        setChatOpenSignal(t => t + 1);
    }, [activeSubtopic, phase, trackBehavior]);

    const taskBlockForMonitor = getTaskBlock();
    const starterCode = taskBlockForMonitor?.starter_code;

    const mentorHelpPayload = useCallback(() => ({
        course_id: course.id,
        subtopic_id: activeSubtopic!.id,
        current_code: latestCodeRef.current || currentCode || undefined,
        starter_code: starterCode || undefined,
    }), [activeSubtopic, course.id, currentCode, starterCode]);
    const { markEdit } = useEditorStallMonitor({
        enabled: phase === "task" && Boolean(activeSubtopic),
        code: currentCode,
        starterCode: taskBlockForMonitor?.starter_code,
        submitLoading,
        mentorPromptPending,
        userDeclinedHelp: mentorDeclined,
        onStall: handleStall,
    });

    const resumeLessonFromServer = useCallback(async (subtopic: Subtopic) => {
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/start", {
                subtopic_id: subtopic.id,
                course_id: course.id,
            });
            applyStartResponse(res.data as ChatBlock);
        } catch {
            setBlocks([]);
            setPhase("idle");
        } finally {
            setLoading(false);
        }
    }, [course.id, applyStartResponse]);

    const openLessonAfterGeneration = useCallback(async (subtopic: Subtopic) => {
        const status = await fetchLessonStatus(subtopic.id);
        if (!status?.generated) {
            throw new Error("Урок ещё не готов — повторите генерацию");
        }

        const res = await axios.post("/workspace/lesson/start", {
            subtopic_id: subtopic.id,
            course_id: course.id,
        });
        applyStartResponse(res.data as ChatBlock);
        if (document.visibilityState !== "visible") {
            notifyLessonReady(subtopic.title, "Урок готов — можно начинать");
        }
    }, [course.id, applyStartResponse, fetchLessonStatus]);

    const pollContentJob = useCallback((
        jobId: string,
        subtopic: Subtopic,
        { autoOpen = true }: { autoOpen?: boolean } = {},
    ) => {
        stopKafkaJobPoll(contentJobPollRef);
        setPhase("generating");
        setLoading(true);

        startKafkaJobPoll(jobId, contentJobPollRef, {
            onDone: async () => {
                try {
                    const status = await fetchLessonStatus(subtopic.id);
                    patchLessonWorkspace({ generating: false, jobId: null });

                    if (!status?.generated) {
                        await axios.post("/workspace/lesson/generate", {
                            subtopic_id: subtopic.id,
                            course_id: course.id,
                        });
                        const retried = await fetchLessonStatus(subtopic.id);
                        if (!retried?.generated) {
                            throw new Error(
                                typeof retried?.message === "string" && retried.message
                                    ? retried.message
                                    : "Не удалось сгенерировать урок. Попробуйте ещё раз.",
                            );
                        }
                    }

                    patchLessonWorkspace({ lessonReady: true });
                    if (autoOpen) {
                        setBlocks([]);
                        await openLessonAfterGeneration(subtopic);
                    } else {
                        setPhase("idle");
                    }
                } catch (err) {
                    const message = extractError(err);
                    setBlocks([{ id: genId(), type: "error", content: message }]);
                    setPhase("idle");
                    patchLessonWorkspace({ generating: false, jobId: null, lessonReady: false });
                } finally {
                    setLoading(false);
                }
            },
            onFailed: (data) => {
                setBlocks([{
                    id: genId(),
                    type: "error",
                    content: typeof data.message === "string" ? data.message : "Ошибка генерации урока",
                }]);
                setPhase("idle");
                patchLessonWorkspace({ generating: false, jobId: null });
                setLoading(false);
            },
        });
    }, [fetchLessonStatus, openLessonAfterGeneration, course.id]);

    useEffect(() => {
        if (restoredRef.current) return;
        restoredRef.current = true;

        (async () => {
            const saved = loadWorkspaceSession(course.id);
            if (saved && isActiveLessonPhase(saved.phase)) {
                const located = findSubtopicInCourse(course, saved.subtopicId);
                if (located) {
                    const statusRes = await axios.post("/workspace/lesson/status", {
                        course_id: course.id,
                        subtopic_id: saved.subtopicId,
                    }).catch(() => null);
                    const status = statusRes?.data as LessonStatus | undefined;

                    if (canRestoreWorkspaceSession(status, saved.phase)) {
                        setActiveSubtopic(located.subtopic);
                        setActiveThemeTitle(saved.themeTitle);
                        const code = saved.currentCode ?? "";
                        setCurrentCode(code);
                        latestCodeRef.current = code;
                        fetchLessonStatus(saved.subtopicId);

                        if (status?.status === "generating_content" && status.job_id) {
                            setPhase("generating");
                            pollContentJob(status.job_id, located.subtopic, {
                                autoOpen: saved.phase !== "idle",
                            });
                            return;
                        }

                        if (status?.generated) {
                            await resumeLessonFromServer(located.subtopic);
                            return;
                        }
                    } else {
                        clearWorkspaceSession(course.id);
                        clearLessonWorkspaceForCourse(course.id);
                    }
                }
            }

            if (!first_unlocked?.id) return;

            const statusRes = await axios.post("/workspace/lesson/status", {
                course_id: course.id,
                subtopic_id: first_unlocked.id,
            }).catch(() => null);

            const resumeId = (statusRes?.data as LessonStatus | undefined)?.in_progress_subtopic_id;
            if (!resumeId) {
                fetchLessonStatus(first_unlocked.id);
                return;
            }

            const located = findSubtopicInCourse(course, resumeId);
            if (!located) return;

            setActiveSubtopic(located.subtopic);
            setActiveThemeTitle(located.themeTitle);
            const status = await fetchLessonStatus(resumeId);

            const savedAgain = loadWorkspaceSession(course.id);
            if (
                savedAgain?.subtopicId === resumeId
                && canRestoreWorkspaceSession(status, savedAgain.phase)
            ) {
                const code = savedAgain.currentCode ?? "";
                setCurrentCode(code);
                latestCodeRef.current = code;
                if (status?.generated) {
                    await resumeLessonFromServer(located.subtopic);
                }
                return;
            }

            if (status?.generated) {
                await resumeLessonFromServer(located.subtopic);
                const draft = status?.draft_code;
                if (draft) {
                    setCurrentCode(draft);
                    latestCodeRef.current = draft;
                }
            }
        })();
    }, [course, first_unlocked?.id, fetchLessonStatus, resumeLessonFromServer, pollContentJob]);

    useEffect(() => {
        generationResumeRef.current = false;
    }, [course.id]);

    useEffect(() => {
        if (!activeSubtopic) return;
        rememberWorkspaceContext(
            course.id,
            activeSubtopic.id,
            activeSubtopic.title,
            activeThemeTitle,
        );
    }, [course.id, activeSubtopic, activeThemeTitle]);

    useEffect(() => {
        if (!activeSubtopic || generationResumeRef.current) return;

        const timer = window.setTimeout(async () => {
            const status = await fetchLessonStatus(activeSubtopic.id);
            const stored = loadLessonWorkspace();

            if (status?.status === "generating_content" && status.job_id) {
                generationResumeRef.current = true;
                if (phase === "idle") {
                    setPhase("generating");
                }
                pollContentJob(status.job_id, activeSubtopic, {
                    autoOpen: phase !== "idle" || Boolean(stored?.lessonReady),
                });
                return;
            }

            if (phase === "generating" && status?.generated) {
                generationResumeRef.current = true;
                setBlocks([]);
                try {
                    setLoading(true);
                    await openLessonAfterGeneration(activeSubtopic);
                } catch (err) {
                    setBlocks([{ id: genId(), type: "error", content: extractError(err) }]);
                    setPhase("idle");
                } finally {
                    setLoading(false);
                }
            }
        }, 150);

        return () => window.clearTimeout(timer);
    }, [activeSubtopic?.id, course.id, phase, fetchLessonStatus, pollContentJob, openLessonAfterGeneration]);

    useEffect(() => () => stopKafkaJobPoll(contentJobPollRef), []);

    useEffect(() => {
        if (!activeSubtopic || !isActiveLessonPhase(phase)) {
            if (phase === "idle") {
                clearWorkspaceSession(course.id);
            }
            return;
        }
        saveWorkspaceSession(course.id, {
            subtopicId: activeSubtopic.id,
            themeTitle: activeThemeTitle,
            phase,
            blocks,
            nextSubtopic,
            currentCode: latestCodeRef.current || currentCode,
        });
    }, [
        course.id,
        activeSubtopic,
        activeThemeTitle,
        phase,
        blocks,
        nextSubtopic,
        currentCode,
    ]);

    useEffect(() => {
        if (phase !== "task" || !activeSubtopic) return;
        const code = latestCodeRef.current || currentCode;
        if (!code.trim()) return;

        const timer = window.setTimeout(() => {
            axios.post("/workspace/lesson/draft", {
                course_id: course.id,
                subtopic_id: activeSubtopic.id,
                code,
            }).catch(() => {});
        }, 2000);

        return () => window.clearTimeout(timer);
    }, [currentCode, phase, activeSubtopic, course.id]);

    const handleSelectSubtopic = useCallback((subtopic: Subtopic, themeTitle: string) => {
        stopKafkaJobPoll(contentJobPollRef);
        generationResumeRef.current = false;
        clearWorkspaceSession(course.id);
        rememberWorkspaceContext(course.id, subtopic.id, subtopic.title, themeTitle);
        patchLessonWorkspace({ generating: false, lessonReady: false, inProgress: false, jobId: null });
        setActiveSubtopic(subtopic);
        setActiveThemeTitle(themeTitle);
        setPhase("idle");
        setBlocks([]);
        setNextSubtopic(null);
        setMentorDeclined(false);
        setCurrentCode("");
        latestCodeRef.current = "";
        setPlanOpen(false);
        fetchLessonStatus(subtopic.id);
    }, [course.id, fetchLessonStatus]);

    const handleBegin = async () => {
        if (!activeSubtopic) return;
        setBlocks([]);
        requestLessonNotificationPermission();

        rememberWorkspaceContext(
            course.id,
            activeSubtopic.id,
            activeSubtopic.title,
            activeThemeTitle,
        );

        try {
            const status = lessonStatus ?? (await fetchLessonStatus(activeSubtopic.id));

            if (status?.generated) {
                setLoading(true);
                try {
                    await openLessonAfterGeneration(activeSubtopic);
                } catch {
                    await axios.post("/workspace/lesson/generate", {
                        subtopic_id: activeSubtopic.id,
                        course_id: course.id,
                    });
                    await fetchLessonStatus(activeSubtopic.id);
                    await openLessonAfterGeneration(activeSubtopic);
                } finally {
                    setLoading(false);
                }
                return;
            }

            if (status?.status === "generating_content" && status.job_id) {
                saveLessonWorkspace({
                    courseId: course.id,
                    subtopicId: activeSubtopic.id,
                    subtopicTitle: activeSubtopic.title,
                    themeTitle: activeThemeTitle,
                    generating: true,
                    jobId: status.job_id,
                });
                pollContentJob(status.job_id, activeSubtopic);
                return;
            }

            setPhase("generating");
            setLoading(true);
            saveLessonWorkspace({
                courseId: course.id,
                subtopicId: activeSubtopic.id,
                subtopicTitle: activeSubtopic.title,
                themeTitle: activeThemeTitle,
                generating: true,
                jobId: null,
            });

            const generateRes = await axios.post("/workspace/lesson/generate", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
            });
            const generateData = generateRes.data as {
                status?: string;
                job_id?: string;
                generated?: boolean;
                from_cache?: boolean;
            };

            if (generateData.generated || generateData.from_cache) {
                await fetchLessonStatus(activeSubtopic.id);
                patchLessonWorkspace({ generating: false, lessonReady: false });
                await openLessonAfterGeneration(activeSubtopic);
                setLoading(false);
                return;
            }

            if (generateData.status === "generating_content" && generateData.job_id) {
                patchLessonWorkspace({ jobId: generateData.job_id });
                pollContentJob(generateData.job_id, activeSubtopic);
                return;
            }

            throw new Error("Не удалось запустить генерацию урока");
        } catch (err) {
            const message = extractError(err);
            if (!message.includes("Сначала нажмите")) {
                setBlocks([{ id: genId(), type: "error", content: message }]);
            } else {
                setBlocks([]);
            }
            setPhase("idle");
            patchLessonWorkspace({ generating: false, jobId: null });
            setLoading(false);
        }
    };

    const handleContinue = async () => {
        if (!activeSubtopic) return;
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/continue", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
                action: "continue",
            });
            const block = res.data as ChatBlock;
            setBlocks(prev => pushLessonBlocks(prev, block));
            setPhase(block.type === "task" ? "task" : "learning");
            if (block.type === "task") {
                setMentorDeclined(false);
            }
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    };

    const handleSendQuestion = async (message: string) => {
        if (!activeSubtopic || mentorChatBlocked) return;
        setBlocks(prev => [...prev, { id: genId(), type: "user_message", message }]);
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/continue", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
                action: "question",
                message,
                current_code: latestCodeRef.current || currentCode || undefined,
            });
            let data = res.data as ChatBlock & { status?: string; job_id?: string; mentor_chat_blocked?: boolean };

            if (data.status === "generating_chat" && data.job_id) {
                data = await waitForKafkaJob(data.job_id, 30_000) as ChatBlock & { mentor_chat_blocked?: boolean };
            }

            if (data.mentor_chat_blocked) {
                setMentorChatBlocked(true);
            }

            setBlocks(prev => pushLessonBlocks(prev, data as ChatBlock));
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    };

    const handleRequestTheory = async () => {
        if (!activeSubtopic) return;
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/continue", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
                action: "request_theory",
            });
            const block = res.data as ChatBlock;
            setBlocks(pushLessonBlocks([], block));
            setPhase("learning");
        } catch (err) {
            const message = extractError(err);
            if (message.includes("Теория недоступна")) {
                clearWorkspaceSession(course.id);
                clearLessonWorkspaceForCourse(course.id);
                setPhase("idle");
                setBlocks([{
                    id: genId(),
                    type: "error",
                    content: "Урок ещё не сгенерирован. Нажмите «Начать урок».",
                }]);
            } else {
                setBlocks(prev => [...prev, { id: genId(), type: "error", content: message }]);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleTheoryBack = async () => {
        if (!activeSubtopic) return;
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/continue", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
                action: "theory_back",
            });
            const block = res.data as ChatBlock;
            setBlocks(prev => pushLessonBlocks(prev, block));
            setPhase("learning");
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    };

    const handleCodeChange = useCallback((code: string) => {
        latestCodeRef.current = code;
        setCurrentCode(code);
    }, []);

    const handleCodeActivity = useCallback((code: string) => {
        latestCodeRef.current = code;
        setCurrentCode(code);
        markEdit(code);
    }, [markEdit]);

    const handleSubmitSolution = async (code: string) => {
        if (!activeSubtopic) return;
        latestCodeRef.current = code;
        setPhase("evaluating");
        setSubmitLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/submit", {
                subtopic_id: activeSubtopic.id,
                course_id: course.id,
                code,
            });

            let data = res.data as Record<string, unknown> & { status?: string; job_id?: string };

            if (data.status === "generating_review" && typeof data.job_id === "string") {
                data = await waitForKafkaJob(data.job_id, 60_000);
            }

            const feedback: ChatBlock = {
                id: genId(),
                type: "solution_feedback",
                is_correct: Boolean(data.is_correct) && !data.partial_pass,
                score: typeof data.score === "number" ? data.score : undefined,
                feedback: typeof data.feedback === "string" ? data.feedback : undefined,
                strengths: Array.isArray(data.strengths) ? data.strengths as string[] : undefined,
                improvements: Array.isArray(data.improvements) ? data.improvements as string[] : undefined,
            };

            setBlocks(prev => [...prev, feedback]);

            if (data.micro_task) {
                const micro = data.micro_task as ChatBlock;
                setBlocks(prev => [...prev, { ...micro, id: genId(), type: "micro_task" }]);
                setPhase("task");
                setMentorDeclined(false);
                return;
            }

            if (data.partial_pass && data.next_task) {
                const nextTask = data.next_task as ChatBlock;
                setBlocks(prev => [
                    ...prev.filter(b => b.type !== "task"),
                    { ...nextTask, id: genId(), type: "task" },
                ]);
                setPhase("task");
                setMentorDeclined(false);
                return;
            }

            if (data.is_correct) {
                setCompletedIds(prev => [...new Set([...prev, activeSubtopic.id])]);
                setNextSubtopic((data.next_subtopic as Subtopic | null | undefined) ?? null);
                setPhase("completed");

                if (data.history_item) {
                    setExerciseHistory(prev => [data.history_item as ExerciseHistoryItem, ...prev]);
                }
            } else {
                setPhase("task");
            }
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
            setPhase("task");
        } finally {
            setSubmitLoading(false);
        }
    };

    const handleNextSubtopic = () => {
        if (!nextSubtopic) return;
        for (const module of course.modules) {
            for (const theme of module.themes) {
                const found = theme.subtopics.find(s => s.id === nextSubtopic.id);
                if (found) {
                    handleSelectSubtopic(found, theme.title);
                    return;
                }
            }
        }
    };

    const handleMentorAction = useCallback(async (action: MentorActionId) => {
        if (!activeSubtopic) return;
        if (action === "decline") {
            setMentorDeclined(true);
        }
        setLoading(true);
        try {
            const res = await axios.post("/workspace/mentor/help", {
                ...mentorHelpPayload(),
                action,
            });
            const block = res.data?.block as ChatBlock | undefined;
            if (block) {
                setBlocks(prev => [...prev, { ...block, id: genId() }]);
            }
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    }, [activeSubtopic, mentorHelpPayload]);

    const handleMentorOption = useCallback(async (optionId: MentorOptionId) => {
        if (!activeSubtopic) return;
        setLoading(true);
        try {
            const res = await axios.post("/workspace/mentor/help", {
                ...mentorHelpPayload(),
                action: "select_option",
                option_id: optionId,
            });
            const block = res.data?.block as ChatBlock | undefined;
            if (block) {
                setBlocks(prev => [...prev, { ...block, id: genId() }]);
            }
        } catch (err) {
            setBlocks(prev => [...prev, { id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    }, [activeSubtopic, mentorHelpPayload]);

    const handleReviewTheory = useCallback(async (subtopicId?: number, slideIndex = 0) => {
        const targetId = subtopicId ?? activeSubtopic?.id;
        if (!targetId) return;

        if (subtopicId) {
            const located = findSubtopicInCourse(course, subtopicId);
            if (located) {
                setActiveSubtopic(located.subtopic);
                setActiveThemeTitle(located.themeTitle);
                await fetchLessonStatus(targetId);
            }
        }

        setArchiveOpen(false);
        setPlanOpen(false);
        setBlocks([]);
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/review", {
                course_id: course.id,
                subtopic_id: targetId,
                slide_index: slideIndex,
            });
            const block = res.data as ChatBlock;
            if (block.type === "lesson_review") {
                setBlocks([{ ...block, id: genId() }]);
                setPhase("lesson_review");
            } else {
                setBlocks(pushLessonBlocks([], block));
                setPhase("theory_review");
            }
        } catch (err) {
            setBlocks([{ id: genId(), type: "error", content: extractError(err) }]);
            setPhase("idle");
        } finally {
            setLoading(false);
        }
    }, [activeSubtopic?.id, course, fetchLessonStatus]);

    const handleReviewContinue = useCallback(async () => {
        const theory = [...blocks].reverse().find(b => b.type === "theory");
        if (!theory || !activeSubtopic) return;
        const nextIndex = theory.part ?? 1;
        await handleReviewTheory(undefined, nextIndex);
    }, [blocks, activeSubtopic, handleReviewTheory]);

    const handleReviewBack = useCallback(async () => {
        const theory = [...blocks].reverse().find(b => b.type === "theory");
        if (!theory || !activeSubtopic) return;
        const prevIndex = Math.max(0, (theory.part ?? 1) - 2);
        await handleReviewTheory(undefined, prevIndex);
    }, [blocks, activeSubtopic, handleReviewTheory]);

    const handleShowLessonSummary = useCallback(async () => {
        if (!activeSubtopic) return;
        setArchiveOpen(false);
        setLoading(true);
        try {
            const res = await axios.post("/workspace/lesson/review", {
                course_id: course.id,
                subtopic_id: activeSubtopic.id,
                slide_index: 99,
            });
            const block = res.data as ChatBlock;
            setBlocks([{ ...block, id: genId() }]);
            setPhase("lesson_review");
        } catch (err) {
            setBlocks([{ id: genId(), type: "error", content: extractError(err) }]);
        } finally {
            setLoading(false);
        }
    }, [activeSubtopic, course.id]);

    const direction = (course as { direction?: { name?: string } }).direction?.name ?? "typescript";

    return (
        <AppLayout>
            <div className="shrink-0 px-6 py-1 border-b border-slate-100 bg-white dark:border-gray-800 dark:bg-gray-950">
                <Breadcrumbs
                    crumbs={[
                        { label: "DevPath", path: "/main" },
                        { label: "Мои курсы", path: "/main" },
                        { label: course.title, path: `/workspace/${course.id}` },
                    ]}
                />
            </div>
            <div className="flex h-[calc(100vh-88px)] flex-col overflow-hidden bg-white dark:bg-gray-950">
                <div className="shrink-0 flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-6 py-3 surface-header dark:border-gray-800 dark:bg-gray-900">
                    <div className="flex items-center gap-3 min-w-0">
                        <Link
                            href="/main"
                            className="shrink-0 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100 transition-colors"
                        >
                            ← К курсам
                        </Link>
                        <div className="min-w-0">
                            <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">Обучение</h1>
                            <p className="text-xs text-gray-400 truncate max-w-md">{course.title}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        {!lessonActive && (
                            <button
                                type="button"
                                onClick={() => {
                                    setArchiveOpen(v => !v);
                                    if (!archiveOpen) setPlanOpen(false);
                                }}
                                className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
                                    archiveOpen
                                        ? "border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-200"
                                        : "border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                }`}
                            >
                                Архив задач
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setPlanOpen(true)}
                            className="rounded-lg border border-violet-200 px-3 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-300 dark:hover:bg-violet-950/40"
                        >
                            План курса
                        </button>
                    </div>
                </div>

                <div className="flex flex-1 min-h-0 overflow-hidden">
                    <main className="flex-1 min-w-0 min-h-0 overflow-hidden flex flex-col">
                        {!lessonActive && archiveOpen ? (
                            <ExerciseArchivePanel
                                items={exerciseHistory}
                                onClose={() => setArchiveOpen(false)}
                                onReviewTheory={id => handleReviewTheory(id, 0)}
                                onOpenSubtopic={id => {
                                    for (const module of course.modules) {
                                        for (const theme of module.themes) {
                                            const found = theme.subtopics.find(s => s.id === id);
                                            if (found) {
                                                handleSelectSubtopic(found, theme.title);
                                                return;
                                            }
                                        }
                                    }
                                }}
                            />
                        ) : (
                        <LessonStudio
                            subtopic={activeSubtopic}
                            themeTitle={activeThemeTitle}
                            direction={direction}
                            phase={phase}
                            blocks={blocks}
                            loading={loading}
                            lessonStatus={lessonStatus}
                            nextSubtopic={nextSubtopic}
                            submitLoading={submitLoading}
                            onBegin={handleBegin}
                            onContinue={handleContinue}
                            onTheoryBack={handleTheoryBack}
                            onSendQuestion={handleSendQuestion}
                            onRequestTheory={handleRequestTheory}
                            onSubmitSolution={handleSubmitSolution}
                            onNextSubtopic={handleNextSubtopic}
                            onCodeChange={handleCodeChange}
                            onCodeActivity={handleCodeActivity}
                            editorCode={currentCode}
                            chatOpenSignal={chatOpenSignal}
                            onMentorAction={handleMentorAction}
                            onMentorOption={handleMentorOption}
                            mentorInteractionLocked={loading || submitLoading}
                            mentorChatBlocked={mentorChatBlocked}
                            onReviewTheory={() => handleReviewTheory(undefined, 0)}
                            onShowLessonSummary={handleShowLessonSummary}
                            onReviewContinue={handleReviewContinue}
                            onReviewBack={handleReviewBack}
                        />
                        )}
                    </main>
                </div>
            </div>

            <CoursePlanDrawer
                open={planOpen}
                onClose={() => setPlanOpen(false)}
                course={course}
                completedIds={completedIds}
                activeSubtopicId={activeSubtopic?.id ?? null}
                onSelectSubtopic={handleSelectSubtopic}
                unlockAll={unlockAllLessons}
            />
        </AppLayout>
    );
}
