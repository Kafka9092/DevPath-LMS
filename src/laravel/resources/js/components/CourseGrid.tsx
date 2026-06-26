import React from "react";
import { CourseCard } from "./CourseCard";
import { COURSE_GRID_CELL_CLASS } from "@/components/course/courseCardLayout";
import { SeniorProgramCard } from "@/components/senior/SeniorProgramCard";

interface Course {
    id: number;
    title: string;
    direction: string;
    level: string;
    progress: number;
}

export interface SeniorProgramInfo {
    direction: string;
}

interface CourseGridProps {
    courses: Course[];
    seniorProgram?: SeniorProgramInfo | null;
}
 
const EmptyIcon = () => (
    <svg className="w-14 h-14 text-violet-200 dark:text-violet-900/60" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
    </svg>
);
 
export const CourseGrid: React.FC<CourseGridProps> = ({ courses, seniorProgram }) => {
    const hasCourses = courses && courses.length > 0;
    const hasSenior = Boolean(seniorProgram?.direction);

    if (!hasSenior && !hasCourses) {
        return (
            <div className="flex flex-col items-center justify-center py-24 gap-4 text-center px-8">
                <EmptyIcon />
                <p className="text-slate-500 dark:text-gray-400 font-medium">У вас пока нет курсов</p>
                <p className="text-sm text-slate-400 dark:text-gray-500">Нажмите «Создать курс», чтобы начать обучение</p>
            </div>
        );
    }

    return (
        <div className="px-8 pt-2 pb-12 min-h-full">
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {hasSenior && <SeniorProgramCard direction={seniorProgram!.direction} />}
                {hasCourses &&
                    courses.map((course) => (
                        <div key={course.id} className={COURSE_GRID_CELL_CLASS}>
                            <CourseCard
                                id={course.id}
                                title={course.title}
                                direction={course.direction}
                                level={course.level}
                                progress={course.progress ?? 0}
                            />
                        </div>
                    ))}
            </div>
        </div>
    );
};
