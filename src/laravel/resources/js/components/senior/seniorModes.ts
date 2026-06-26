import { router } from "@inertiajs/react";
import {
    buildInterviewUrl,
    buildPlanSettingsUrl,
    buildSeniorHubUrl,
    markSeniorModeTried,
    SeniorModeId,
} from "@/lib/seniorProgramStorage";

export interface SeniorModeOption {
    id: SeniorModeId;
    title: string;
    description: string;
}

export const SENIOR_MODES: SeniorModeOption[] = [
    {
        id: "learning",
        title: "Стандартное обучение",
        description: "План курса и онбординг",
    },
    {
        id: "interview",
        title: "Собеседование",
        description: "Тренировка с AI HR",
    },
];

export function navigateSeniorMode(direction: string, mode: SeniorModeId): void {
    markSeniorModeTried(direction, mode);
    switch (mode) {
        case "learning":
            router.visit(buildPlanSettingsUrl(direction));
            break;
        case "interview":
            router.visit(buildInterviewUrl(direction));
            break;
    }
}

export function openSeniorHub(direction: string): void {
    router.visit(buildSeniorHubUrl(direction));
}
