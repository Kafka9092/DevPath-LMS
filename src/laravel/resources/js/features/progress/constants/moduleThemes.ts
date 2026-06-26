export type ModuleId = "learning" | "interviews" | "codeReview";

export interface ModuleTheme {
    id: ModuleId;
    label: string;
    primary: string;
    primaryLight: string;
    ring: string;
    text: string;
    textDark: string;
    bgSoft: string;
    tailwindBar: string;
    legendDot: string;
}

export const MODULE_THEMES: Record<ModuleId, ModuleTheme> = {
    learning: {
        id: "learning",
        label: "Обучение",
        primary: "#2563eb",
        primaryLight: "#3b82f6",
        ring: "ring-blue-200 dark:ring-blue-900",
        text: "text-blue-700 dark:text-blue-400",
        textDark: "text-blue-600",
        bgSoft: "bg-blue-50 dark:bg-blue-950/40",
        tailwindBar: "bg-blue-500",
        legendDot: "bg-blue-500",
    },
    interviews: {
        id: "interviews",
        label: "Собеседования",
        primary: "#7c3aed",
        primaryLight: "#8b5cf6",
        ring: "ring-violet-200 dark:ring-violet-900",
        text: "text-violet-700 dark:text-violet-400",
        textDark: "text-violet-600",
        bgSoft: "bg-violet-50 dark:bg-violet-950/40",
        tailwindBar: "bg-violet-500",
        legendDot: "bg-violet-500",
    },
    codeReview: {
        id: "codeReview",
        label: "Code Review",
        primary: "#ea580c",
        primaryLight: "#f97316",
        ring: "ring-orange-200 dark:ring-orange-900",
        text: "text-orange-700 dark:text-orange-400",
        textDark: "text-orange-600",
        bgSoft: "bg-orange-50 dark:bg-orange-950/40",
        tailwindBar: "bg-orange-500",
        legendDot: "bg-orange-500",
    },
};

export const MODULE_TABS: { id: ModuleId; label: string }[] = [
    { id: "learning", label: "Обучение" },
    { id: "interviews", label: "Собеседования" },
    { id: "codeReview", label: "Code Review" },
];


export const MODULE_CHART_COLORS = {
    learning: MODULE_THEMES.learning.primary,
    interviews: MODULE_THEMES.interviews.primary,
    codeReview: MODULE_THEMES.codeReview.primary,
} as const;
