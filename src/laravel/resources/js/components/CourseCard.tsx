import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { getLanguageVisual } from "@/lib/courseLanguageVisual";
import { CourseCardBrandArt } from "@/components/course/CourseCardBrandArt";
import { LanguageLogoBadge } from "@/components/course/LanguageLogoBadge";
import {
    COURSE_CARD_ARTICLE_CLASS,
    COURSE_CARD_CLIP_CLASS,
    COURSE_CARD_CONTENT_WIDTH_CLASS,
    COURSE_CARD_FOOTER_CLASS,
    COURSE_CARD_MIDDLE_MIN_H_CLASS,
    COURSE_CARD_ELEVATION_CLASS,
    COURSE_CARD_SHELL_CLASS,
} from "@/components/course/courseCardLayout";
import { DeleteCourseModal } from "./DeleteCourseModal";
import { clearLessonWorkspaceForCourse } from "@/lib/lessonWorkspaceStorage";
import { clearWorkspaceSession } from "@/lib/workspaceSession";

export interface CourseCardData {
    id: number;
    title: string;
    direction: string;
    level: string;
    progress: number;
}

const IconSettings = () => (
    <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={1.75} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.216-.456c-.354-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.354.133.75.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
);

const IconTrash = () => (
    <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={1.75} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
    </svg>
);

const ArrowRight = () => (
    <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
    </svg>
);

export const CourseCard: React.FC<CourseCardData> = ({ id, title, direction, progress }) => {
    const visual = getLanguageVisual(direction);
    const pct = Math.min(100, Math.max(0, Math.round(progress)));
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/courses/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                clearWorkspaceSession(id);
                clearLessonWorkspaceForCourse(id);
            },
            onFinish: () => {
                setDeleting(false);
                setDeleteOpen(false);
            },
        });
    };

    return (
        <>
            <article className={`${COURSE_CARD_ARTICLE_CLASS} ${COURSE_CARD_ELEVATION_CLASS}`}>
                <div className={COURSE_CARD_CLIP_CLASS}>
                <CourseCardBrandArt direction={direction} />

                {}
                <div className={`${COURSE_CARD_SHELL_CLASS} ${COURSE_CARD_CONTENT_WIDTH_CLASS}`}>
                    <div className="mb-3">
                        <LanguageLogoBadge direction={direction} size={44} />
                    </div>

                    <h3 className="line-clamp-2 text-base font-bold leading-snug text-slate-900 dark:text-gray-100">
                        {title}
                    </h3>
                    <p className="mb-4 mt-0.5 text-[11px] font-semibold uppercase tracking-widest text-slate-500 dark:text-gray-400">
                        {visual.label}
                    </p>

                    {}
                    <div className={`${COURSE_CARD_MIDDLE_MIN_H_CLASS} w-full max-w-full`}>
                        <div className="mb-1.5 flex items-center justify-between gap-2 text-xs">
                            <span className="font-medium text-slate-500 dark:text-gray-400">Прогресс</span>
                            <span className="shrink-0 font-bold tabular-nums text-violet-600 dark:text-violet-400">
                                {pct}%
                            </span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-gray-700">
                            <div
                                className="h-full rounded-full bg-violet-600 transition-all duration-500 dark:bg-violet-500"
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                    </div>

                    <div className={`${COURSE_CARD_FOOTER_CLASS} border-slate-100 dark:border-gray-800`}>
                        <button
                            type="button"
                            onClick={() => router.visit(`/workspace/${id}`)}
                            className="flex items-center gap-2 text-sm font-semibold transition-all duration-200 hover:gap-3"
                            style={{ color: visual.accent }}
                        >
                            Продолжить обучение
                            <ArrowRight />
                        </button>

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => router.visit(`/courses/${id}/settings`)}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-violet-950/40 dark:hover:text-violet-300"
                            >
                                <IconSettings />
                                Настройки
                            </button>
                            <button
                                type="button"
                                onClick={() => setDeleteOpen(true)}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                            >
                                <IconTrash />
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>
                </div>
            </article>

            <DeleteCourseModal
                open={deleteOpen}
                title={title}
                onConfirm={handleDelete}
                onCancel={() => setDeleteOpen(false)}
                loading={deleting}
            />
        </>
    );
};
