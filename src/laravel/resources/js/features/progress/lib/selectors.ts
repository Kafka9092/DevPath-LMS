import type {
    CodeReviewStats,
    InterviewStats,
    LanguageRow,
    LearningStats,
    ProgressPageProps,
} from "@/types/progress";
import type { ModuleId } from "../constants/moduleThemes";
import { PLATFORM_LANGUAGES } from "../constants/platformLanguages";

export type LanguageOverviewRow = {
    language: string;
    lessons: number;
    interviews: number;
    reviews: number;
    total: number;
};

export function buildLanguageOverview(props: ProgressPageProps): LanguageOverviewRow[] {
    const map = new Map<string, LanguageOverviewRow>();

    for (const row of props.learning.by_language ?? []) {
        const lang = String(row.language);
        const entry = map.get(lang) ?? { language: lang, lessons: 0, interviews: 0, reviews: 0, total: 0 };
        entry.lessons = Number(row.lessons_touched ?? 0);
        map.set(lang, entry);
    }
    for (const row of props.interviews.by_language ?? []) {
        const lang = String(row.language);
        const entry = map.get(lang) ?? { language: lang, lessons: 0, interviews: 0, reviews: 0, total: 0 };
        entry.interviews = Number(row.total ?? 0);
        map.set(lang, entry);
    }
    for (const row of props.codeReview.by_language ?? []) {
        const lang = String(row.language);
        const entry = map.get(lang) ?? { language: lang, lessons: 0, interviews: 0, reviews: 0, total: 0 };
        entry.reviews = Number(row.total ?? 0);
        map.set(lang, entry);
    }

    return PLATFORM_LANGUAGES.map((lang) => {
        const r = map.get(lang) ?? { language: lang, lessons: 0, interviews: 0, reviews: 0, total: 0 };
        return { ...r, total: r.lessons + r.interviews + r.reviews };
    }).sort((a, b) => b.total - a.total || a.language.localeCompare(b.language));
}

export function languagesForModule(moduleId: ModuleId, data: ProgressPageProps): LanguageRow[] {
    switch (moduleId) {
        case "learning":
            return data.learning.by_language ?? [];
        case "interviews":
            return data.interviews.by_language ?? [];
        case "codeReview":
            return data.codeReview.by_language ?? [];
    }
}

export function hasModuleData(moduleId: ModuleId, data: ProgressPageProps): boolean {
    switch (moduleId) {
        case "learning":
            return data.learning.summary.lessons_touched > 0;
        case "interviews":
            return data.interviews.summary.total > 0;
        case "codeReview":
            return data.codeReview.summary.total > 0;
    }
}

export type ModuleKpi = {
    label: string;
    value: string | number;
    hint?: string;
};

export function buildModuleKpis(
    moduleId: ModuleId,
    learning: LearningStats,
    interviews: InterviewStats,
    codeReview: CodeReviewStats,
): ModuleKpi[] {
    switch (moduleId) {
        case "learning": {
            const s = learning.summary;
            const progress = s.course_progress_pct ?? s.completion_rate;
            return [
                { label: "Уроков начато", value: s.lessons_touched },
                {
                    label: "Завершено уроков",
                    value: s.lessons_completed,
                    hint: `${progress}% от начатых`,
                },
                {
                    label: "Средний балл за ДЗ",
                    value: s.avg_score ?? "—",
                },
                {
                    label: "Подсказок",
                    value: s.hints_used,
                },
            ];
        }
        case "interviews": {
            const s = interviews.summary;
            return [
                { label: "Всего интервью", value: s.total },
                {
                    label: "Завершено",
                    value: s.completed,
                    hint: s.stopped_early > 0 ? `Досрочно: ${s.stopped_early}` : undefined,
                },
                {
                    label: "Принято HR",
                    value: s.successful,
                    hint: s.total > 0 ? `${s.success_rate}% успешности` : undefined,
                },
                {
                    label: "Оценка hard skills",
                    value: s.ai_hard_skills_score != null ? `${s.ai_hard_skills_score}/100` : "—",
                },
            ];
        }
        case "codeReview": {
            const s = codeReview.summary;
            return [
                { label: "Проверок кода", value: s.total },
                {
                    label: "Средний балл",
                    value: s.avg_score ?? "—",
                    hint: "Шкала 0–100",
                },
                {
                    label: "Лучший балл",
                    value: s.best_score ?? "—",
                },
                {
                    label: "Балл ≥ 70",
                    value: s.high_score_count,
                    hint: s.total > 0 ? `${s.high_score_rate}% проверок` : undefined,
                },
            ];
        }
    }
}
