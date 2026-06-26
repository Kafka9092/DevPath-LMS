import React from "react";
import { OnboardingCard } from "./OnboardingCard";
import { getDomainsForDirection } from "../types/OnboardingTypes";
import { IconBolt, IconScale, IconBook } from "./OnboardingIcons";
import { LearningPreference } from "../types/OnboardingTypes";

interface Step1Props {
    value: LearningPreference | null;
    onChange: (v: LearningPreference) => void;
}

const OPTIONS: {
    value: LearningPreference;
    label: string;
    description: string;
    icon: React.ReactNode;
}[] = [
    {
        value: "practice_heavy",
        label: "Больше практики",
        description: "Короткий блок теории (2 слайда), затем задача. Меньше текста, чем в режиме «Баланс», но с базой перед практикой.",
        icon: <IconBolt className="w-5 h-5" />,
    },
    {
        value: "balanced",
        label: "Баланс",
        description: "Короткая выжимка теории перед каждым блоком задач. Оптимально для большинства.",
        icon: <IconScale className="w-5 h-5" />,
    },
    {
        value: "theory_heavy",
        label: "Больше теории",
        description: "Разжёванные концепции и примеры кода перед практикой. Для тех, кто любит понять «почему».",
        icon: <IconBook className="w-5 h-5" />,
    },
];

export const Step1Learning: React.FC<Step1Props> = ({ value, onChange }) => {
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
