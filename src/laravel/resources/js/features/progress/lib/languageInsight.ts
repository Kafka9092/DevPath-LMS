import type { LanguageStat } from "@/types/progress";

export function buildLanguageInsight(stat: LanguageStat): string {
    const { learning_score = 0, interview_score = 0, review_score = 0 } = stat;

    if (!stat.has_activity) {
        return "Начните обучение, симуляцию или Code Review — и здесь появится персональная аналитика.";
    }

    const reviewStrong = review_score >= 70;
    const reviewWeak = review_score > 0 && review_score < 50;
    const learningStrong = learning_score >= 70;
    const learningWeak = learning_score > 0 && learning_score < 45;
    const interviewStrong = interview_score >= 70;
    const interviewWeak = interview_score > 0 && interview_score < 50;

    if (reviewStrong && learningWeak) {
        return "Замечательное качество кода, но рекомендуем подтянуть теоретическую базу в Обучении.";
    }
    if (learningStrong && interviewWeak) {
        return "Теория и практика в порядке — добавьте симуляций собеседований для уверенности на интервью.";
    }
    if (interviewStrong && reviewWeak) {
        return "Сильные soft/hard skills на интервью; уделите внимание чистоте кода в Code Review.";
    }
    if (reviewStrong && interviewStrong && learningStrong) {
        return "Сбалансированный профиль по всем модулям — отличная динамика для выхода на рынок.";
    }
    if (review_score === 0 && interview_score === 0 && learning_score > 0) {
        return "Хороший старт в обучении — попробуйте AI-собеседование или ревью кода для полной картины.";
    }

    return "Продолжайте чередовать уроки, симуляции и ревью — индекс растёт быстрее при активности во всех модулях.";
}
