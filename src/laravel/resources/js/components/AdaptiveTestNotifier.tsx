import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Link, usePage } from "@inertiajs/react";
import {
    ADAPTIVE_TEST_STORAGE_EVENT,
    buildTestUrl,
    loadAnyStoredTest,
    clearStoredTest,
    dismissTestBanner,
    isTestBannerDismissed,
    markFirstQuestionReadySeen,
    patchStoredTest,
} from "@/lib/adaptiveTestStorage";
import { GENERATING_STATUSES, isSessionReturnable } from "@/lib/testSessionPoll";

type NoticeKind = "generating_first" | "ready_first" | "in_progress" | "results";

interface NoticeState {
    sessionId: string;
    direction: string;
    message: string;
    kind: NoticeKind;
}

function maybeShowBrowserNotification(title: string, body: string): void {
    if (typeof window === "undefined" || document.visibilityState === "visible") return;
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    try {
        new Notification(title, { body, tag: "devpath-test-ready" });
    } catch {
        /* ignore */
    }
}

export const AdaptiveTestNotifier: React.FC = () => {
    const { url } = usePage();
    const [notice, setNotice] = useState<NoticeState | null>(null);
    const readyPingedRef = useRef(false);

    const dismiss = useCallback(() => {
        if (notice) {
            dismissTestBanner(notice.kind);
        } else {
            markFirstQuestionReadySeen();
        }
        setNotice(null);
        readyPingedRef.current = false;
    }, [notice]);

    useEffect(() => {
        let timeoutId = 0;
        let cancelled = false;

        const tick = async () => {
            const onTestPage = window.location.pathname.startsWith("/test");

            const stored = loadAnyStoredTest();
            if (!stored?.sessionId) {
                setNotice(null);
                readyPingedRef.current = false;
                return;
            }

            if (onTestPage) {
                setNotice(null);
                return;
            }

            try {
                const res = await axios.get(`/test/session/${stored.sessionId}`);
                const data = res.data;

                if (data.error && data.status === "missing") {
                    clearStoredTest();
                    setNotice(null);
                    readyPingedRef.current = false;
                    return;
                }

                if (stored.screen === "results" && data.is_finished && data.result) {
                    if (!isTestBannerDismissed(stored, "results")) {
                        setNotice({
                            sessionId: stored.sessionId,
                            direction: stored.direction,
                            message: "Результаты теста — можно посмотреть итог",
                            kind: "results",
                        });
                    } else {
                        setNotice(null);
                    }
                    return;
                }

                const questionReady = Boolean(data.question) && data.status === "ready";
                const awaitingFirst =
                    stored.generatingFirstQuestion
                    || data.status === "generating_start"
                    || (questionReady && stored.questionId == null && !stored.notifiedReady);

                if (questionReady && awaitingFirst && !stored.notifiedReady) {
                    if (isTestBannerDismissed(stored, "ready_first")) {
                        setNotice(null);
                        return;
                    }
                    const message = "Первый вопрос готов — можно начинать тест";
                    patchStoredTest({ generatingFirstQuestion: false });
                    setNotice({
                        sessionId: stored.sessionId,
                        direction: stored.direction,
                        message,
                        kind: "ready_first",
                    });
                    if (!readyPingedRef.current) {
                        readyPingedRef.current = true;
                        maybeShowBrowserNotification("DevPath", message);
                    }
                    return;
                }

                if (!isSessionReturnable(data)) {
                    setNotice(null);
                    return;
                }

                if (GENERATING_STATUSES.has(data.status ?? "")) {
                    readyPingedRef.current = false;
                    const isFirst = data.status === "generating_start" || stored.generatingFirstQuestion;
                    const kind = isFirst ? "generating_first" : "in_progress";
                    if (isTestBannerDismissed(stored, kind)) {
                        setNotice(null);
                        return;
                    }
                    setNotice({
                        sessionId: stored.sessionId,
                        direction: stored.direction,
                        message: isFirst
                            ? "Генерируем первый вопрос"
                            : "Тест генерируется — можно вернуться, когда будет готов",
                        kind,
                    });
                    return;
                }

                if (isTestBannerDismissed(stored, "in_progress")) {
                    setNotice(null);
                    return;
                }

                setNotice({
                    sessionId: stored.sessionId,
                    direction: stored.direction,
                    message: "У вас незавершённый тест",
                    kind: "in_progress",
                });
            } catch {
                if (stored.generatingFirstQuestion) {
                    if (!isTestBannerDismissed(stored, "generating_first")) {
                        setNotice({
                            sessionId: stored.sessionId,
                            direction: stored.direction,
                            message: "Генерируем первый вопрос",
                            kind: "generating_first",
                        });
                    } else {
                        setNotice(null);
                    }
                    return;
                }
                if (isTestBannerDismissed(stored, "in_progress")) {
                    setNotice(null);
                    return;
                }
                setNotice({
                    sessionId: stored.sessionId,
                    direction: stored.direction,
                    message: "У вас незавершённый тест",
                    kind: "in_progress",
                });
            }
        };

        const scheduleNext = () => {
            if (cancelled) return;
            const stored = loadAnyStoredTest();
            const delay = stored?.generatingFirstQuestion ? 3000 : 8000;
            timeoutId = window.setTimeout(async () => {
                await tick();
                scheduleNext();
            }, delay);
        };

        void tick().then(scheduleNext);

        const onStorage = () => {
            void tick();
        };
        window.addEventListener("storage", onStorage);
        window.addEventListener(ADAPTIVE_TEST_STORAGE_EVENT, onStorage);

        return () => {
            cancelled = true;
            window.clearTimeout(timeoutId);
            window.removeEventListener("storage", onStorage);
            window.removeEventListener(ADAPTIVE_TEST_STORAGE_EVENT, onStorage);
        };
    }, [url]);

    if (!notice) return null;

    const isReady = notice.kind === "ready_first";
    const isGenerating = notice.kind === "generating_first";
    const isResults = notice.kind === "results";

    return (
        <div
            className={`fixed bottom-6 right-6 z-[100] max-w-sm rounded-xl border shadow-xl px-4 py-3 flex items-start gap-3 ${
                isReady
                    ? "border-emerald-300 bg-emerald-50 dark:bg-gray-900"
                    : isResults
                      ? "border-slate-300 bg-white dark:bg-gray-900"
                      : "border-violet-200 bg-white dark:bg-gray-900"
            }`}
            role="status"
        >
            {!isGenerating && (
                <div
                    className={`w-2.5 h-2.5 mt-2 rounded-full shrink-0 ${
                        isReady ? "bg-emerald-500" : "bg-violet-500"
                    }`}
                />
            )}
            <div className="min-w-0 flex-1">
                <p
                    className={`text-sm font-semibold ${
                        isReady
                            ? "text-emerald-900 dark:text-emerald-100"
                            : "text-gray-900 dark:text-gray-100"
                    }`}
                >
                    {isGenerating ? "Генерируем первый вопрос" : notice.message}
                </p>
                <Link
                    href={buildTestUrl(notice.sessionId, notice.direction)}
                    onClick={dismiss}
                    className={`text-sm font-medium mt-1 inline-block ${
                        isReady
                            ? "text-emerald-700 hover:text-emerald-900"
                            : "text-violet-600 hover:text-violet-800"
                    }`}
                >
                    {isReady ? "Перейти к тесту →" : isResults ? "Открыть результаты →" : "Вернуться к тесту →"}
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
