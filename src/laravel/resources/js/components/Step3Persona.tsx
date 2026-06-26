import React from "react";
import { OnboardingCard } from "./OnboardingCard";
import { getDomainsForDirection } from "../types/OnboardingTypes";
import { IconUserCircle, IconUsers, IconHeart } from "./OnboardingIcons";
import { MentorPersona  } from "../types/OnboardingTypes";

interface Step3Props {
    value: MentorPersona | null;
    onChange: (v: MentorPersona) => void;
}

const OPTIONS: {
    value: MentorPersona;
    label: string;
    description: string;
    icon: React.ReactNode;
}[] = [
    {
        value: "strict_lead",
        label: "Строгий тимлид",
        description: "Критичный разбор каждой строчки. Упор на Clean Code, паттерны и оптимизацию. Никакой поблажки.",
        icon: <IconUserCircle className="w-5 h-5" />,
    },
    {
        value: "colleague",
        label: "Коллега",
        description: "Общение на равных. Дружелюбные подсказки по делу, без лишней строгости и без лишней мягкости.",
        icon: <IconUsers className="w-5 h-5" />,
    },
    {
        value: "soft_mentor",
        label: "Мягкий ментор",
        description: "Максимум поддержки. Бережный фидбек, похвала за прогресс, акцент на понимание, а не на ошибки.",
        icon: <IconHeart className="w-5 h-5" />,
    },
];

export const Step3Persona: React.FC<Step3Props> = ({ value, onChange }) => {
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
