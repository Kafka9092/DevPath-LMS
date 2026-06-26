import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Link, usePage } from "@inertiajs/react";
import {
    LESSON_WORKSPACE_STORAGE_EVENT,
    dismissLessonNotice,
    isLessonNoticeDismissed,
    loadLessonWorkspace,
    patchLessonWorkspace,
    workspaceHref,
    type LessonNoticeKind,
} from "@/lib/lessonWorkspaceStorage";
import { notifyLessonReady } from "@/lib/lessonNotifications";
import type { LessonStatus } from "@/components/lesson/LessonStudio";

interface NoticeState {
    courseId: number;
    subtopicTitle: string;
    message: string;
    kind: LessonNoticeKind;
}

export const LessonGenerationNotifier: React.FC = () => {
    const { url } = usePage();
    const [notice, setNotice] = useState<NoticeState | null>(null);
    const readyPingedRef = useRef(false);
    const tickingRef = useRef(false);

    const dismiss = useCallback(() => {
        if (notice) {
            dismissLessonNotice(notice.kind);
        }
        setNotice(null);
        readyPingedRef.current = false;
    }, [notice]);

    useEffect(() => {
        let timeoutId = 0;
        let cancelled = false;

        const tick = async () => {
            if (tickingRef.current) {
                return;
            }
            tickingRef.current = true;

            try {
            const stored = loadLessonWorkspace();
            if (!stored) {
                setNotice(null);
                readyPingedRef.current = false;
                return;
            }

            const path = window.location.pathname;
            if (path.startsWith("/test")) {
                setNotice(null);
                return;
            }

            const onWorkspace = path.startsWith(`/workspace/${stored.courseId}`);

            if (onWorkspace) {
                setNotice(null);
                return;
            }

            try {
                const res = await axios.post("/workspace/lesson/status", {
                    course_id: stored.courseId,
                    subtopic_id: stored.subtopicId,
                });
                const status = res.data as LessonStatus;

                const generating =
                    stored.generating
                    || status.status === "generating_content"
                    || (!status.generated && Boolean(stored.jobId));

                if (generating) {
                    if (isLessonNoticeDismissed(stored, "generating")) {
                        setNotice(null);
                        return;
                    }
                    readyPingedRef.current = false;
                    if (status.job_id && status.job_id !== stored.jobId) {
                        patchLessonWorkspace({ jobId: status.job_id, generating: true });
                    }
                    setNotice({
                        courseId: stored.courseId,
                        subtopicTitle: stored.subtopicTitle,
                        message: `Генерируем урок «${stored.subtopicTitle}»`,
                        kind: "generating",
                    });
                    return;
                }

                const ready = status.generated && (stored.lessonReady || stored.generating);
                if (ready && !stored.notifiedReady) {
                    if (isLessonNoticeDismissed(stored, "ready")) {
                        setNotice(null);
                        return;
                    }
                    const message = "Урок готов — можно начинать";
                    setNotice({
                        courseId: stored.courseId,
                        subtopicTitle: stored.subtopicTitle,
                        message,
                        kind: "ready",
                    });
                    patchLessonWorkspace({ generating: false, lessonReady: true });
                    if (!readyPingedRef.current) {
                        readyPingedRef.current = true;
                        notifyLessonReady(stored.subtopicTitle, message);
                    }
                    return;
                }

                if (status.in_progress && stored.inProgress) {
                    if (isLessonNoticeDismissed(stored, "in_progress")) {
                        setNotice(null);
                        return;
                    }
                    setNotice({
                        courseId: stored.courseId,
                        subtopicTitle: stored.subtopicTitle,
                        message: `Продолжите урок «${stored.subtopicTitle}»`,
                        kind: "in_progress",
                    });
                    return;
                }

                if (!status.in_progress && !stored.inProgress && status.generated) {
                    patchLessonWorkspace({ generating: false, lessonReady: false, inProgress: false });
                }

                setNotice(null);
            } catch {
                if (stored.generating && !isLessonNoticeDismissed(stored, "generating")) {
                    setNotice({
                        courseId: stored.courseId,
                        subtopicTitle: stored.subtopicTitle,
                        message: `Генерируем урок «${stored.subtopicTitle}»`,
                        kind: "generating",
                    });
                } else {
                    setNotice(null);
                }
            }
            } finally {
                tickingRef.current = false;
            }
        };

        const scheduleNext = () => {
            if (cancelled) return;
            const stored = loadLessonWorkspace();
            const delay = stored?.generating ? 3000 : 8000;
            timeoutId = window.setTimeout(async () => {
                await tick();
                scheduleNext();
            }, delay);
        };

        void tick().then(scheduleNext);

        const onStorage = () => {
            if (cancelled) return;
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(() => {
                void tick().then(scheduleNext);
            }, 400);
        };
        window.addEventListener("storage", onStorage);
        window.addEventListener(LESSON_WORKSPACE_STORAGE_EVENT, onStorage);

        return () => {
            cancelled = true;
            window.clearTimeout(timeoutId);
            window.removeEventListener("storage", onStorage);
            window.removeEventListener(LESSON_WORKSPACE_STORAGE_EVENT, onStorage);
        };
    }, [url]);

    if (!notice) return null;

    const isReady = notice.kind === "ready";

    return (
        <div
            className={`fixed bottom-6 right-6 z-[100] max-w-sm rounded-xl border shadow-xl px-4 py-3 flex items-start gap-3 ${
                isReady
                    ? "border-emerald-300 bg-emerald-50 dark:bg-gray-900"
                    : "border-violet-200 bg-white dark:bg-gray-900"
            }`}
            role="status"
        >
            <div
                className={`w-2.5 h-2.5 mt-2 rounded-full shrink-0 ${
                    isReady ? "bg-emerald-500" : "bg-violet-500 animate-pulse"
                }`}
            />
            <div className="min-w-0 flex-1">
                <p
                    className={`text-sm font-semibold ${
                        isReady
                            ? "text-emerald-900 dark:text-emerald-100"
                            : "text-gray-900 dark:text-gray-100"
                    }`}
                >
                    {notice.message}
                </p>
                <Link
                    href={workspaceHref(notice.courseId)}
                    onClick={() => {
                        if (isReady) {
                            patchLessonWorkspace({ notifiedReady: true, lessonReady: false });
                        }
                        setNotice(null);
                    }}
                    className={`text-sm font-medium mt-1 inline-block ${
                        isReady
                            ? "text-emerald-700 hover:text-emerald-900"
                            : "text-violet-600 hover:text-violet-800"
                    }`}
                >
                    {isReady ? "Открыть урок →" : "Вернуться к уроку →"}
                </Link>
            </div>
            <button
                type="button"
                onClick={dismiss}
                className="shrink-0 -mr-1 -mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                aria-label="Закрыть"
            >
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    );
};
