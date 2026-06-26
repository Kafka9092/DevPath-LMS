import React, { useState } from "react";
import { Course, Subtopic } from "../types/WorkspaceTypes";
 

const LockIcon = () => (
    <svg className="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
    </svg>
);
 
const CheckIcon = () => (
    <svg className="w-3.5 h-3.5 shrink-0 text-violet-600" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
    </svg>
);
 
const ChevronDown = ({ open }: { open: boolean }) => (
    <svg className={`w-3.5 h-3.5 text-slate-400 transition-transform duration-200 ${open ? "rotate-180" : ""}`} fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
    </svg>
);
 
const PlayIcon = () => (
    <svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 24 24">
        <path d="M8 5.14v14l11-7-11-7z" />
    </svg>
);
 

interface CoursePlanProps {
    course: Course;
    completedIds: number[];
    activeSubtopicId: number | null;
    onSelectSubtopic: (subtopic: Subtopic, themeTitle: string) => void;
    unlockAll?: boolean;
}
 
function isUnlocked(subtopicId: number, course: Course, completedIds: number[], unlockAll = false): boolean {
    if (unlockAll) return true;
    const allSubtopics: number[] = [];
    [...course.modules]
        .sort((a, b) => a.module_number - b.module_number)
        .forEach(m =>
            [...m.themes].sort((a, b) => a.order - b.order).forEach(t =>
                [...t.subtopics].sort((a, b) => a.order - b.order).forEach(s =>
                    allSubtopics.push(s.id)
                )
            )
        );
 
    if (allSubtopics.length === 0) return false;
    if (allSubtopics[0] === subtopicId) return true;
    const pos = allSubtopics.indexOf(subtopicId);
    if (pos < 0) return false;
    return completedIds.includes(allSubtopics[pos - 1]);
}
 
export const CoursePlan: React.FC<CoursePlanProps> = ({
    course, completedIds, activeSubtopicId, onSelectSubtopic, unlockAll = false,
}) => {
    const [openModules, setOpenModules] = useState<Set<number>>(() => {
        const first = course.modules[0]?.id;
        return first ? new Set([first]) : new Set();
    });
    const [openThemes, setOpenThemes] = useState<Set<number>>(new Set(
        course.modules.flatMap(m => m.themes.map(t => t.id))
    ));
 
    const toggleModule = (id: number) => {
        setOpenModules(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };
 
    const toggleTheme = (id: number) => {
        setOpenThemes(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };
 
    const totalSubtopics  = course.modules.flatMap(m => m.themes.flatMap(t => t.subtopics)).length;
    const completedCount  = completedIds.length;
    const progressPct     = totalSubtopics > 0 ? Math.round((completedCount / totalSubtopics) * 100) : 0;
 
    return (
        <div className="flex flex-col h-full">
            {}
            <div className="px-4 pt-4 pb-3 border-b border-slate-100 shrink-0">
                <h2 className="font-bold text-slate-900 text-sm mb-0.5 truncate">{course.title}</h2>
                <div className="flex items-center gap-2 mt-2">
                    <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            className="h-full bg-violet-500 rounded-full transition-all duration-500"
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                    <span className="text-xs text-slate-400 font-medium shrink-0">
                        {completedCount}/{totalSubtopics}
                    </span>
                </div>
            </div>
 
            {}
            <div className="flex-1 overflow-y-auto py-2">
                {[...course.modules].sort((a, b) => a.module_number - b.module_number).map(module => {
                    const moduleOpen = openModules.has(module.id);
                    const moduleCompleted = module.themes.every(t =>
                        t.subtopics.every(s => completedIds.includes(s.id))
                    );
 
                    return (
                        <div key={module.id} className="mb-0.5">
                            {}
                            <button
                                onClick={() => toggleModule(module.id)}
                                className="w-full flex items-center gap-2 px-4 py-2.5 hover:bg-slate-50 transition-colors text-left"
                            >
                                <span className={`w-5 h-5 rounded-md flex items-center justify-center text-xs font-bold shrink-0 ${
                                    moduleCompleted
                                        ? "bg-violet-100 text-violet-700"
                                        : "bg-slate-100 text-slate-500"
                                }`}>
                                    {module.module_number}
                                </span>
                                <span className="flex-1 text-xs font-semibold text-slate-700 leading-tight truncate">
                                    {module.title}
                                </span>
                                <ChevronDown open={moduleOpen} />
                            </button>
 
                            {}
                            {moduleOpen && [...module.themes].sort((a, b) => a.order - b.order).map(theme => {
                                const themeOpen  = openThemes.has(theme.id);
                                const themeCompleted = theme.subtopics.every(s => completedIds.includes(s.id));
 
                                return (
                                    <div key={theme.id} className="ml-4">
                                        <button
                                            onClick={() => toggleTheme(theme.id)}
                                            className="w-full flex items-center gap-2 px-3 py-2 hover:bg-slate-50 transition-colors text-left"
                                        >
                                            {themeCompleted
                                                ? <CheckIcon />
                                                : <span className="w-3.5 h-3.5 rounded-sm border border-slate-200 shrink-0" />
                                            }
                                            <span className={`flex-1 text-xs leading-tight truncate ${
                                                themeCompleted ? "text-violet-600 font-medium" : "text-slate-600"
                                            }`}>
                                                {theme.title}
                                            </span>
                                            <ChevronDown open={themeOpen} />
                                        </button>
 
                                        {}
                                        {themeOpen && [...theme.subtopics].sort((a, b) => a.order - b.order).map(subtopic => {
                                            const completed = completedIds.includes(subtopic.id);
                                            const unlocked  = isUnlocked(subtopic.id, course, completedIds, unlockAll);
                                            const isActive  = activeSubtopicId === subtopic.id;
 
                                            return (
                                                <button
                                                    key={subtopic.id}
                                                    disabled={!unlocked}
                                                    onClick={() => unlocked && onSelectSubtopic(subtopic, theme.title)}
                                                    className={`
                                                        w-full flex items-center gap-2 pl-8 pr-3 py-2 text-left
                                                        transition-all duration-150 rounded-lg mx-1
                                                        ${isActive
                                                            ? "bg-violet-100 text-violet-800"
                                                            : unlocked
                                                                ? "hover:bg-slate-50 text-slate-600 cursor-pointer"
                                                                : "cursor-not-allowed"
                                                        }
                                                    `}
                                                >
                                                    <span className={`shrink-0 ${
                                                        completed    ? "text-violet-600"
                                                        : !unlocked  ? "text-slate-300"
                                                        : isActive   ? "text-violet-600"
                                                        : "text-slate-400"
                                                    }`}>
                                                        {completed   ? <CheckIcon /> :
                                                         !unlocked   ? <LockIcon /> :
                                                         isActive    ? <PlayIcon /> :
                                                         <span className="w-3.5 h-3.5 block rounded-full border border-slate-300" />}
                                                    </span>
                                                    <span className={`text-xs leading-tight flex-1 truncate ${
                                                        !unlocked ? "text-slate-300" : ""
                                                    }`}>
                                                        {subtopic.title}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                            })}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
