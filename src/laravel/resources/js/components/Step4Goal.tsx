import React from "react";
import { OnboardingCard } from "./OnboardingCard";
import { getDomainsForDirection } from "../types/OnboardingTypes";
import { IconRocketLaunch, IconBriefcase, IconLightBulb } from "./OnboardingIcons";
import { CareerGoal   } from "../types/OnboardingTypes";

interface Step4Props {
    value: CareerGoal | null;
    onChange: (v: CareerGoal) => void;
}

const OPTIONS: {
    value: CareerGoal;
    label: string;
    description: string;
    icon: React.ReactNode;
}[] = [
    {
        value: "senior_interview",
        label: "Пройти собеседование на Senior",
        description: "Хочу попасть в бигтех или сильную продуктовую компанию. Готов к хардкору и алгоритмам.",
        icon: <IconRocketLaunch className="w-5 h-5" />,
    },
    {
        value: "mid_level_confidence",
        label: "Уверенно работать на текущем уровне",
        description: "Хочу закрыть пробелы в знаниях и перестать гуглить базовые вещи на Middle-позиции.",
        icon: <IconBriefcase className="w-5 h-5" />,
    },
    {
        value: "startup_architecture",
        label: "Создать архитектуру своего стартапа",
        description: "Пишу свой продукт и хочу понимать, как строить его правильно с нуля до продакшена.",
        icon: <IconLightBulb className="w-5 h-5" />,
    },
];

export const Step4Goal: React.FC<Step4Props> = ({ value, onChange }) => {
    return (
        <div className="space-y-3">
            {OPTIONS.map(opt => (
                <OnboardingCard
                    key={opt.value}
                    selected={value === opt.value}
                    onClick={() => onChange(opt.value)}
                    icon={opt.icon}
                    label={opt.label}
                    description={opt.description}
                />
            ))}
        </div>
    );
};
