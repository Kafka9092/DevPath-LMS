import React from "react";
import { OnboardingCard } from "./OnboardingCard";
import { getDomainsForDirection } from "../types/OnboardingTypes";
import { DOMAIN_ICONS, IconGlobe } from "./OnboardingIcons";

interface Step2Props {
    direction: string;
    value: string | null;
    onChange: (v: string) => void;
}

export const Step2Domain: React.FC<Step2Props> = ({ direction, value, onChange }) => {
    const domains = getDomainsForDirection(direction);

    return (
        <div className="space-y-3">
            {}
            <div className="flex items-center gap-2 px-3 py-2.5 bg-violet-50 border border-violet-100 rounded-xl mb-4">
                <span className="w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0" />
                <p className="text-xs text-violet-600 font-medium">
                    Домены подобраны под <span className="font-bold">{direction}</span> — ИИ будет генерировать задачи именно в этом контексте
                </p>
            </div>

            {domains.map(domain => {
                const Icon = DOMAIN_ICONS[domain.value] ?? IconGlobe;
                return (
                    <OnboardingCard
                        key={domain.value}
                        selected={value === domain.value}
                        onClick={() => onChange(domain.value)}
                        icon={<Icon className="w-5 h-5" />}
                        label={domain.label}
                        description={domain.description}
                    />
                );
            })}
        </div>
    );
};
