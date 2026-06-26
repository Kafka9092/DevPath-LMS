
export const LANGUAGE_INDEX_WEIGHTS = {
    learning: 0.25,
    interviews: 0.35,
    codeReview: 0.4,
} as const;

export function computeLanguageProgressIndex(
    learningScore: number,
    interviewScore: number,
    reviewScore: number,
): number {
    const raw =
        learningScore * LANGUAGE_INDEX_WEIGHTS.learning +
        interviewScore * LANGUAGE_INDEX_WEIGHTS.interviews +
        reviewScore * LANGUAGE_INDEX_WEIGHTS.codeReview;

    return Math.round(raw * 10) / 10;
}

export function progressGradeLabel(index: number): string {
    if (index >= 85) return "Senior";
    if (index >= 65) return "Middle";
    if (index >= 45) return "Junior";
    if (index >= 25) return "Beginner";
    return "Старт";
}

export function formatTrendChange(change: number): { text: string; tone: "up" | "flat" | "down" } {
    if (change > 0) {
        return { text: `+${change} с начала`, tone: "up" };
    }
    if (change < 0) {
        return { text: `${change} с начала`, tone: "down" };
    }
    return { text: "— 0 с начала", tone: "flat" };
}

export function hasLanguageActivity(stat: {
    lessons_total?: number;
    interviews?: number;
    code_reviews?: number;
    has_activity?: boolean;
}): boolean {
    if (stat.has_activity != null) return stat.has_activity;
    return (
        (stat.lessons_total ?? 0) > 0 ||
        (stat.interviews ?? 0) > 0 ||
        (stat.code_reviews ?? 0) > 0
    );
}

export function displayLanguageIndex(stat: {
    start_value: number;
    current_value: number;
    has_activity?: boolean;
    lessons_total?: number;
    interviews?: number;
    code_reviews?: number;
}): number {
    if (!hasLanguageActivity(stat)) {
        return stat.start_value;
    }
    return stat.current_value;
}
