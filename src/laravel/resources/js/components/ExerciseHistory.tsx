import React, { useState } from "react";
import { ExerciseHistoryItem } from "../types/WorkspaceTypes";

const CheckCircle = () => (
    <svg className="w-4 h-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
);

interface ExerciseHistoryProps {
    items: ExerciseHistoryItem[];
    onSelect?: (subtopicId: number) => void;
}

export const ExerciseHistory: React.FC<ExerciseHistoryProps> = ({ items, onSelect }) => {
    const [expandedId, setExpandedId] = useState<number | null>(null);

    return (
        <div className="flex flex-col h-full">
            <div className="px-4 pt-4 pb-3 border-b border-slate-100 shrink-0">
                <h2 className="font-bold text-slate-900 text-sm">Пройденные уроки</h2>
                <p className="text-xs text-slate-400 mt-0.5">{items.length} завершено</p>
            </div>

            <div className="flex-1 overflow-y-auto py-2">
                {items.length === 0 ? (
                    <div className="px-4 py-8 text-center">
                        <p className="text-xs text-slate-400 leading-relaxed">
                            Здесь появятся задачи и ваш код после успешного решения
                        </p>
                    </div>
                ) : (
                    items.map((item, idx) => {
                        const open = expandedId === item.subtopic_id;
                        return (
                            <div key={`${item.subtopic_id}-${idx}`} className="border-b border-slate-50">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setExpandedId(open ? null : item.subtopic_id);
                                        onSelect?.(item.subtopic_id);
                                    }}
                                    className="w-full flex items-start gap-3 px-4 py-2.5 hover:bg-violet-50 transition-colors text-left"
                                >
                                    <CheckCircle />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-medium text-slate-800 truncate">
                                            {item.task_title || item.subtopic_title}
                                        </p>
                                        <p className="text-[10px] text-slate-400 truncate">{item.theme_title}</p>
                                        {item.lesson_score != null && (
                                            <span className="inline-block mt-1 text-[10px] font-bold text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded">
                                                {item.lesson_score} баллов
                                            </span>
                                        )}
                                    </div>
                                </button>
                                {open && (
                                    <div className="px-4 pb-3 space-y-2 bg-slate-50/80">
                                        {item.task_description && (
                                            <p className="text-[10px] text-slate-600 leading-snug line-clamp-4">
                                                {item.task_description}
                                            </p>
                                        )}
                                        {item.submitted_code && (
                                            <pre className="text-[9px] bg-slate-900 text-slate-200 rounded-lg p-2 max-h-32 overflow-auto font-mono">
                                                {item.submitted_code}
                                            </pre>
                                        )}
                                        {item.lesson_feedback && (
                                            <p className="text-[10px] text-violet-700">{item.lesson_feedback}</p>
                                        )}
                                        <p className="text-[10px] text-slate-400">{item.completed_at}</p>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
};
