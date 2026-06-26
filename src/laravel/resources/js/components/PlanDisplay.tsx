import React from 'react';

interface Subtopic {
    id: number;
    title: string;
}

interface Theme {
    id: number;
    title: string;
    description: string;
    subtopics: Subtopic[];
}

interface Module {
    id: number;
    module_number: number;
    title: string;
    description: string;
    themes: Theme[];
}

interface PlanDisplayProps {
    plan: {
        title: string;
        description: string;
        modules: Module[];
    };
}

export const PlanDisplay: React.FC<PlanDisplayProps> = ({ plan }) => {
    return (
        <div>
            <p>{plan.description}</p>
            {plan.modules.map((module) => (
                <div key={module.id}>
                    <h3>Модуль {module.module_number}. {module.title}</h3>
                    <p>{module.description}</p>
                    {module.themes.map((theme, themeIdx) => (
                        <div key={theme.id}>
                            <p><strong>{themeIdx + 1}. {theme.title}</strong></p>
                            <p>{theme.description}</p>
                            <ul>
                                {theme.subtopics.map((subtopic, subtopicIdx) => (
                                    <li key={subtopic.id}>
                                        {themeIdx + 1}.{subtopicIdx + 1} {subtopic.title}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            ))}

            <p><em>Если вам не подходит этот план обучения, вы можете пройти тест заново — и мы подберём программу, которая лучше соответствует вашему уровню.</em></p>
        </div>
    );
};
