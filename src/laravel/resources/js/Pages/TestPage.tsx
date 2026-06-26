import React from "react";
import { router } from "@inertiajs/react";
import { Test } from "@/components/Test";
import { AppLayout } from '@/components/Sidebar';
import { Breadcrumbs } from '@/components/Breadcrumbs';

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

import { abandonStoredTest } from "@/lib/adaptiveTestStorage";
import { testBreadcrumbs } from "@/lib/courseFlowBreadcrumbs";

export default function TestPage({
    direction,
    session: initialSession,
    fresh = false,
}: {
    direction: string;
    session?: string | null;
    fresh?: boolean;
}) {
    const handleContinue = (overallLevel?: string) => {
        abandonStoredTest();
        const dir = encodeURIComponent(direction.toUpperCase());
        if ((overallLevel ?? "").toLowerCase() === "senior") {
            router.visit(`/senior-program?direction=${dir}`);
            return;
        }
        router.visit(`/plan-settings?direction=${dir}`);
    };

    const dir = directionLabel(direction);

    return (
        <AppLayout>
            <div className="w-full px-6 lg:px-10">
                <Breadcrumbs crumbs={testBreadcrumbs(dir)} />
            </div>
            <div className="px-6 pb-2 pt-1 lg:px-10 max-w-4xl mx-auto">
                <h1 className="text-xl md:text-2xl font-extrabold text-slate-800 dark:text-gray-100 tracking-tight">
                    Тест для определения текущего уровня подготовки
                </h1>
                <p className="text-sm text-slate-500 dark:text-gray-400 mt-0.5">
                    Направление: <span className="font-semibold text-violet-600 dark:text-violet-400">{dir}</span>
                </p>
            </div>
            <div className="flex min-h-[calc(100vh-180px)] items-start justify-center px-6 pb-8 pt-8 md:pt-12 lg:px-10 max-w-4xl mx-auto w-full">
                <Test
                    direction={direction}
                    initialSessionId={initialSession ?? undefined}
                    forceFresh={fresh}
                    onContinue={handleContinue}
                />
            </div>
        </AppLayout>
    );
}
