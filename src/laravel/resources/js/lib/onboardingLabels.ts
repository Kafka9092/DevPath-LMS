import type { CareerGoal, LearningPreference, MentorPersona } from "@/types/OnboardingTypes";
import { getDomainsForDirection } from "@/types/OnboardingTypes";

export const LEARNING_LABELS: Record<LearningPreference, string> = {
    practice_heavy: "Больше практики",
    balanced: "Баланс теории и практики",
    theory_heavy: "Больше теории",
};

export const PERSONA_LABELS: Record<MentorPersona, string> = {
    strict_lead: "Строгий тимлид",
    colleague: "Коллега-наставник",
    soft_mentor: "Мягкий ментор",
};

export const GOAL_LABELS: Record<CareerGoal, string> = {
    senior_interview: "Подготовка к Senior-интервью",
    mid_level_confidence: "Уверенность Middle-уровня",
    startup_architecture: "Архитектура для стартапа",
};

export function domainLabel(direction: string, value: string | null): string {
    if (!value) return "—";
    const domains = getDomainsForDirection(direction);
    return domains.find((d) => d.value === value)?.label ?? value;
}
