import React, { useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { AppLayout } from '@/components/Sidebar';
import { Breadcrumbs } from '@/components/Breadcrumbs';
import { DirectionSelector } from '@/components/DirectionSelector';
import { InfoText } from '@/components/InfoText';
import { ActionButtons } from '@/components/ActionButtons';
import { abandonStoredTest, buildFreshTestUrl } from '@/lib/adaptiveTestStorage';
import { createCourseBreadcrumbs } from '@/lib/courseFlowBreadcrumbs';

const directions = [
    'PHP', 'Python', 'JavaScript', 'TypeScript', 'Java', 'C++', 'C#', 'Go', 'Ruby',
];

const DIRECTION_STORAGE_KEY = 'devpath_create_course_direction';

function loadStoredDirection(): string | null {
    try {
        const stored = localStorage.getItem(DIRECTION_STORAGE_KEY);
        if (stored && directions.includes(stored)) {
            return stored;
        }
    } catch {
    }
    return null;
}

export default function CreateCourse() {
    const [selectedDirection, setSelectedDirection] = useState<string | null>(loadStoredDirection);

    const handleSelectDirection = useCallback((direction: string) => {
        setSelectedDirection(direction);
        try {
            localStorage.setItem(DIRECTION_STORAGE_KEY, direction);
        } catch {
        }
    }, []);

    const handleStartTest = () => {
        if (selectedDirection) {
            abandonStoredTest();
            router.visit(buildFreshTestUrl(selectedDirection));
        }
    };

    const handleCreatePlanFromScratch = () => {
        if (selectedDirection) {
            abandonStoredTest();
            void axios.post('/test/prepare-retake').finally(() => {
                router.visit(
                    `/plan-settings?direction=${encodeURIComponent(selectedDirection)}&from_scratch=1`,
                );
            });
        }
    };

    return (
        <AppLayout>
            <div className="w-full px-6 lg:px-10">
                <Breadcrumbs crumbs={createCourseBreadcrumbs()} />
            </div>
            <div
                className={`flex min-h-[calc(100vh-88px)] flex-col items-center justify-center px-4 py-8 transition-transform duration-300 ease-out ${
                    selectedDirection ? '-translate-y-10' : ''
                }`}
            >
                <div className="w-full max-w-xl">
                    <div className="mb-10 text-center">
                        <h1 className="text-3xl font-extrabold text-slate-800 dark:text-gray-100 leading-tight mb-3 tracking-tight">
                            Выберите язык{' '}
                            <span className="text-violet-600">программирования</span>
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-gray-400 leading-relaxed">
                            Выберите язык — мы подберём программу под ваш уровень или поможем
                            выстроить план с нуля.
                        </p>
                    </div>

                    <div className="mb-8">
                        <DirectionSelector
                            directions={directions}
                            selectedDirection={selectedDirection}
                            onSelect={handleSelectDirection}
                        />
                    </div>

                    <div className="min-h-[220px]">
                        {selectedDirection && (
                            <div className="animate-[fadeUp_0.2s_ease_both]">
                                <InfoText direction={selectedDirection} />
                                <ActionButtons
                                    onStartTest={handleStartTest}
                                    onCreatePlan={handleCreatePlanFromScratch}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </AppLayout>
    );
}
