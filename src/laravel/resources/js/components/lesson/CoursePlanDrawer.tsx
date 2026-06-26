import React from "react";
import { Course } from "../../types/WorkspaceTypes";
import { CoursePlan } from "../CoursePlan";

interface Props {
    open: boolean;
    onClose: () => void;
    course: Course;
    completedIds: number[];
    activeSubtopicId: number | null;
    onSelectSubtopic: (subtopic: import("../../types/WorkspaceTypes").Subtopic, themeTitle: string) => void;
    catBanner?: React.ReactNode;
    unlockAll?: boolean;
}

export const CoursePlanDrawer: React.FC<Props> = ({
    open, onClose, course, completedIds, activeSubtopicId, onSelectSubtopic, catBanner, unlockAll = false,
}) => (
    <>
        <button
            type="button"
            onClick={() => open && onClose()}
            className={`fixed inset-0 z-40 bg-slate-900/40 transition-opacity ${open ? "opacity-100" : "opacity-0 pointer-events-none"}`}
            aria-hidden={!open}
        />
        <aside
            className={`fixed top-0 right-0 z-50 h-full w-72 max-w-[90vw] bg-white dark:bg-gray-900 border-l border-slate-200 dark:border-gray-800 shadow-2xl flex flex-col transition-transform duration-300 ease-out ${
                open ? "translate-x-0" : "translate-x-full"
            }`}
        >
            <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-gray-800">
                <span className="font-semibold text-slate-900 dark:text-gray-100 text-sm">План курса</span>
                <button type="button" onClick={onClose} className="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-gray-800 text-slate-500 dark:text-gray-400">✕</button>
            </div>
            {catBanner}
            <div className="flex-1 overflow-hidden">
                <CoursePlan
                    course={course}
                    completedIds={completedIds}
                    activeSubtopicId={activeSubtopicId}
                    onSelectSubtopic={(s, t) => { onSelectSubtopic(s, t); onClose(); }}
                    unlockAll={unlockAll}
                />
            </div>
        </aside>
    </>
);
