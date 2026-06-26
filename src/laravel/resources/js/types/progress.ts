export interface LanguageTimelinePoint {
    date: string;
    label: string;
    value: number;
    kind?: "start" | "lesson" | "interview" | "review" | "now" | "event";
    module?: string;
    title?: string;
    result?: string;
    tooltip?: string;
}

export interface LanguageKpi {
    time_label: string;
    accuracy_pct: number | null;
    code_quality_label: string | null;
    market_readiness_pct: number;
}

export interface LanguageStat {
    language: string;
    start_value: number;
    current_value: number;
    change: number;
    trend: "up" | "down" | "flat";
    lessons_completed: number;
    lessons_total: number;
    interviews: number;
    code_reviews: number;
    learning_score: number;
    interview_score: number;
    review_score: number;
    grade: string;
    has_activity: boolean;
    kpi: LanguageKpi;
    timeline: LanguageTimelinePoint[];
}

export interface ActivityDay {
    date: string;
    label: string;
    learning: number;
    interviews: number;
    code_review: number;
    total: number;
}

export interface ProgressOverview {
    total_actions: number;
    active_days: number;
    languages: { language: string; actions: number }[];
    activity: ActivityDay[];
    courses_count: number;
    learning_pct: number;
    interview_pct: number;
    code_review_pct: number;
}

export interface LanguageRow {
    language: string;
    [key: string]: string | number | null | undefined;
}

export interface BucketRow {
    label: string;
    count: number;
    min?: number;
    max?: number;
}

export interface CriticalCategory {
    label: string;
    count: number;
}

export interface LearningStats {
    summary: {
        lessons_touched: number;
        lessons_completed: number;
        completion_rate: number;
        course_progress_pct?: number;
        avg_score: number | null;
        hints_used: number;
        courses_active: number;
    };
    by_language: LanguageRow[];
    score_buckets: BucketRow[];
    recent: { title: string; language: string; score: number | null; date: string | null }[];
}

export interface InterviewStats {
    summary: {
        total: number;
        completed: number;
        stopped_early: number;
        successful: number;
        rejected: number;
        success_rate: number;
        avg_duration_min: number | null;
        avg_messages: number | null;
        code_tasks: number;
        ai_hard_skills_score?: number | null;
        weak_topics?: string[];
    };
    by_language: LanguageRow[];
    by_level: { level: string; total: number; completed: number }[];
    decisions: { label: string; value: number; color: string }[];
    star_scores: { label: string; key: string; avg: number }[];
    recent: { direction: string; level: string; decision: string; date: string | null }[];
}

export interface CodeReviewStats {
    summary: {
        total: number;
        avg_score: number | null;
        best_score: number | null;
        high_score_count: number;
        high_score_rate: number;
        total_issues: number;
        clean_code_index?: number | null;
        issues_per_100_loc?: number;
        critical_categories?: CriticalCategory[];
    };
    by_language: LanguageRow[];
    score_buckets: BucketRow[];
    score_trend: { week: string; avg: number; count: number }[];
    recent: { language: string; score: number | null; grade: string | null; date: string }[];
}

export interface ProgressPageProps {
    languages: LanguageStat[];
    overview: ProgressOverview;
    learning: LearningStats;
    interviews: InterviewStats;
    codeReview: CodeReviewStats;
}
