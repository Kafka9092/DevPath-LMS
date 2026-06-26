import React, { useEffect, useState } from "react";
import axios from "axios";
import { Link, usePage } from "@inertiajs/react";
import {
    ADAPTIVE_TEST_STORAGE_EVENT,
    buildTestUrl,
    loadAnyStoredTest,
    clearStoredTest,
    shouldShowMainTestContinueBar,
} from "@/lib/adaptiveTestStorage";
import { SIDEBAR_WIDTH } from "@/components/Sidebar";

const DIRECTION_LABEL: Record<string, string> = {
    php: "PHP",
    python: "Python",
    javascript: "JavaScript",
    typescript: "TypeScript",
    java: "Java",
    "c++": "C++",
    "c#": "C#",
    go: "Go",
    ruby: "Ruby",
};

function directionLabel(dir: string): string {
    const key = dir.toLowerCase();
    return DIRECTION_LABEL[key] ?? dir.toUpperCase();
}

export const AdaptiveTestMainContinue: React.FC = () => {
    const { url } = usePage();
    const [bar, setBar] = useState<{
        sessionId: string;
        direction: string;
        title: string;
        subtitle: string;
    } | null>(null);

    useEffect(() => {
        if (!url.startsWith("/main")) {
            setBar(null);
            return;
        }

        let intervalId = 0;

        const tick = async () => {
            const stored = loadAnyStoredTest();
            if (!stored?.sessionId) {
                setBar(null);
                return;
            }

            try {
                const res = await axios.get(`/test/session/${stored.sessionId}`);
                const data = res.data;

                if (data.error && data.status === "missing") {
                    clearStoredTest();
                    setBar(null);
                    return;
                }

                if (!shouldShowMainTestContinueBar(stored, data)) {
                    setBar(null);
                    return;
                }

                let title = "Продолжить тест";
                let subtitle = `Адаптивный тест · ${directionLabel(stored.direction)}`;

                if (data.status === "generating_start" || data.status === "generating_next" || data.status === "generating_task") {
                    title = "Тест генерируется";
                    subtitle = `${directionLabel(stored.direction)} — вернитесь, когда будет готов`;
                } else if (
                    stored.generatingFirstQuestion
                    && data.status === "ready"
                    && data.question
                ) {
                    title = "Первый вопрос готов";
                    subtitle = `${directionLabel(stored.direction)} — можно продолжить тест`;
                }

                setBar({
                    sessionId: stored.sessionId,
                    direction: stored.direction,
                    title,
                    subtitle,
                });
            } catch {
                if (!shouldShowMainTestContinueBar(stored, {})) {
                    setBar(null);
                    return;
                }
                setBar({
                    sessionId: stored.sessionId,
                    direction: stored.direction,
                    title: "Продолжить тест",
                    subtitle: `Адаптивный тест · ${directionLabel(stored.direction)}`,
                });
            }
        };

        tick();
        intervalId = window.setInterval(tick, 8000);

        const onStorage = () => {
            void tick();
        };
        window.addEventListener(ADAPTIVE_TEST_STORAGE_EVENT, onStorage);

        return () => {
            window.clearInterval(intervalId);
            window.removeEventListener(ADAPTIVE_TEST_STORAGE_EVENT, onStorage);
        };
    }, [url]);

    if (!bar) return null;

    return (
        <div
            className="fixed bottom-0 right-0 z-50 border-t border-violet-200 dark:border-violet-800 bg-violet-50/95 dark:bg-violet-950/90 backdrop-blur-sm px-6 py-4 flex flex-wrap items-center justify-between gap-4 shadow-[0_-4px_24px_rgba(124,58,237,0.12)]"
            style={{ left: SIDEBAR_WIDTH }}
            role="region"
            aria-label="Незавершённый тест"
        >
            <div className="min-w-0">
                <p className="text-sm font-semibold text-violet-900 dark:text-violet-100">{bar.title}</p>
                <p className="text-xs text-violet-600 dark:text-violet-300 mt-0.5">{bar.subtitle}</p>
            </div>
            <Link
                href={buildTestUrl(bar.sessionId, bar.direction)}
                className="shrink-0 px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-orange-500 text-white text-sm font-semibold shadow-md shadow-violet-200/50 transition-colors"
            >
                Продолжить →
            </Link>
        </div>
    );
};
