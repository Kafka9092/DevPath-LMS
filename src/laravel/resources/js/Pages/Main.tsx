import { useEffect } from 'react';
import { AppLayout } from '@/components/Sidebar';
import { Breadcrumbs } from '@/components/Breadcrumbs';
import { CourseGrid } from '@/components/CourseGrid';
import { Header } from '@/components/Header';
import { AdaptiveTestMainContinue } from '@/components/AdaptiveTestMainContinue';
import { saveSeniorProgram } from '@/lib/seniorProgramStorage';
import { router, usePage } from '@inertiajs/react';

interface Course {
    id: number;
    title: string;
    direction: string;
    level: string;
    progress: number;
}

interface SeniorProgramInfo {
    direction: string;
}

interface MainProps {
    courses: Course[];
    seniorProgram?: SeniorProgramInfo | null;
}

export default function MainPage({ courses, seniorProgram }: MainProps) {
    const showTestContinueBar = (usePage().props.features as { adaptiveTestContinueBar?: boolean } | undefined)
        ?.adaptiveTestContinueBar ?? false;

    useEffect(() => {
        if (seniorProgram?.direction) {
            saveSeniorProgram({
                direction: seniorProgram.direction,
                triedModes: [],
                lastMode: null,
            });
        }
    }, [seniorProgram?.direction]);

    const handleCreateCourse = () => {
        router.visit('/create-course');
    };

    const breadcrumbs = [
        { label: 'DevPath', path: '/main' },
        { label: 'Мои курсы', path: '/main' },
    ];

    return (
        <AppLayout>
            <div className="px-8">
                <Breadcrumbs crumbs={breadcrumbs} />
            </div>
            <Header
                title="Мои курсы"
                buttonText="Создать курс"
                onButtonClick={handleCreateCourse}
            />
            <CourseGrid courses={courses} seniorProgram={seniorProgram} />
            {showTestContinueBar && (
                <>
                    <div className="h-24" aria-hidden />
                    <AdaptiveTestMainContinue />
                </>
            )}
        </AppLayout>
    );
}
