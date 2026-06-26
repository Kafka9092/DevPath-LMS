import React, { useState } from "react";
import { getLanguageVisual } from "@/lib/courseLanguageVisual";
import { CourseCardBrandArt } from "@/components/course/CourseCardBrandArt";
import {
    COURSE_CARD_ARTICLE_CLASS,
    COURSE_CARD_CLIP_CLASS,
    COURSE_CARD_CONTENT_WIDTH_CLASS,
    COURSE_CARD_SHELL_CLASS,
    COURSE_GRID_CELL_CLASS,
    SENIOR_CARD_BORDER_CLASS,
    SENIOR_CARD_ELEVATION_CLASS,
} from "@/components/course/courseCardLayout";
import { SeniorModesGrid } from "@/components/senior/SeniorModesGrid";
import { saveSeniorProgram } from "@/lib/seniorProgramStorage";

interface SeniorProgramCardProps {
    direction: string;
}

const IconStar = () => (
    <svg viewBox="0 0 24 24" fill="none" className="w-6 h-6 text-violet-600" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
    </svg>
);

export const SeniorProgramCard: React.FC<SeniorProgramCardProps> = ({ direction }) => {
    const visual = getLanguageVisual(direction);
    const [open, setOpen] = useState(false);

    const toggleOpen = () => {
        if (!open) {
            saveSeniorProgram({ direction, triedModes: [], lastMode: null });
        }
        setOpen((v) => !v);
    };

    return (
        <div
            className={`${COURSE_GRID_CELL_CLASS} ${open ? "sm:col-span-2 lg:col-span-3" : ""}`}
        >
        <article className={`${COURSE_CARD_ARTICLE_CLASS} ${SENIOR_CARD_BORDER_CLASS} ${SENIOR_CARD_ELEVATION_CLASS}`}>
            <div className={COURSE_CARD_CLIP_CLASS}>
            <CourseCardBrandArt direction={direction} />

            <div
                className={`${COURSE_CARD_SHELL_CLASS} ${
                    open ? "max-w-none" : COURSE_CARD_CONTENT_WIDTH_CLASS
                }`}
            >
                <div className="mb-3 flex items-center gap-3">
                    <div className="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center shrink-0 dark:bg-violet-950/40">
                        <IconStar />
                    </div>
                    <span className="text-[10px] font-bold uppercase tracking-widest text-violet-600 bg-violet-50 px-2 py-1 rounded-full dark:bg-violet-950/30 dark:text-violet-400">
                        Senior
                    </span>
                </div>

                <h3 className="text-base font-bold leading-snug text-slate-900 dark:text-gray-100">
                    Программа Senior
                </h3>
                <p className="mb-4 mt-0.5 text-[11px] font-semibold uppercase tracking-widest text-slate-500 dark:text-gray-400">
                    {visual.label}
                </p>

                {!open && (
                    <div className="mb-4 min-h-0 flex-1">
                        <p className="text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                            Два режима: обучение и собеседование. Откройте карточку в
                            любой момент.
                        </p>
                    </div>
                )}

                <div className={`mt-auto border-t border-violet-100 pt-4 dark:border-violet-900/40 ${open ? "mt-4" : ""}`}>
                    <button
                        type="button"
                        onClick={toggleOpen}
                        className="flex items-center gap-2 text-sm font-semibold text-violet-600 transition-colors hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300"
                    >
                        {open ? "Свернуть" : "Открыть программу"}
                        <svg
                            className={`h-4 w-4 transition-transform ${open ? "rotate-180" : ""}`}
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth={2}
                            stroke="currentColor"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </div>

                {open && (
                    <div className="mt-5 border-t border-violet-100 pt-4 dark:border-violet-900/40">
                        <p className="mb-3 text-center text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400">
                            Выберите режим
                        </p>
                        <SeniorModesGrid direction={direction} compact />
                    </div>
                )}
            </div>
            </div>
        </article>
        </div>
    );
};
