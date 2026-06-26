import React, { useState } from "react";
import { XMarkIcon } from "@heroicons/react/24/outline";
import { ExerciseHistoryItem } from "../../types/WorkspaceTypes";

interface Props {
    items: ExerciseHistoryItem[];
    onClose: () => void;
    onReviewTheory: (subtopicId: number) => void;
    onOpenSubtopic: (subtopicId: number) => void;
}

export const ExerciseArchivePanel: React.FC<Props> = ({
    items,
    onClose,
    onReviewTheory,
    onOpenSubtopic,
}) => {
    const [selectedId, setSelectedId] = useState<number | null>(items[0]?.subtopic_id ?? null);
    const selected = items.find(i => i.subtopic_id === selectedId) ?? null;

    return (
        <div className="h-full min-h-0 flex flex-col bg-white dark:bg-gray-900">
            <div className="shrink-0 flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Архив задач</h2>
                    <p className="text-sm text-gray-500 mt-0.5">{items.length} завершённых уроков</p>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    <XMarkIcon className="w-4 h-4" />
                    Закрыть
                </button>
            </div>

            <div className="flex-1 min-h-0 flex overflow-hidden">
                <div className="w-72 shrink-0 border-r border-gray-200 dark:border-gray-800 overflow-y-auto">
                    {items.length === 0 ? (
                        <p className="px-4 py-10 text-sm text-gray-400 text-center">
                            Здесь появятся решённые задачи после успешной сдачи
                        </p>
                    ) : (
                        items.map(item => {
                            const active = item.subtopic_id === selectedId;
                            return (
                                <button
                                    key={item.subtopic_id}
                                    type="button"
                                    onClick={() => {
                                        setSelectedId(item.subtopic_id);
                                        onOpenSubtopic(item.subtopic_id);
                                    }}
                                    className={`w-full text-left px-4 py-3 border-b border-gray-100 dark:border-gray-800 transition-colors ${
                                        active
                                            ? "bg-violet-50 dark:bg-violet-950/40 border-l-2 border-l-violet-600"
                                            : "hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    }`}
                                >
                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        {item.task_title || item.subtopic_title}
                                    </p>
                                    <p className="text-xs text-gray-400 truncate mt-0.5">{item.theme_title}</p>
                                    {item.lesson_score != null && (
                                        <span className="inline-block mt-1.5 text-[10px] font-semibold text-violet-700 bg-violet-100 dark:bg-violet-950/60 px-1.5 py-0.5 rounded">
                                            {item.lesson_score} баллов
                                        </span>
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>

                <div className="flex-1 min-h-0 overflow-y-auto p-6">
                    {!selected ? (
                        <p className="text-sm text-gray-400">Выберите урок слева</p>
                    ) : (
                        <div className="max-w-3xl space-y-5">
                            <div>
                                <p className="text-xs text-gray-400 uppercase tracking-wider">{selected.theme_title}</p>
                                <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                                    {selected.task_title || selected.subtopic_title}
                                </h3>
                                {selected.completed_at && (
                                    <p className="text-xs text-gray-400 mt-1">Завершено: {selected.completed_at}</p>
                                )}
                            </div>

                            {selected.task_description && (
                                <div className="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                                    <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Условие</p>
                                    <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">
                                        {selected.task_description}
                                    </p>
                                </div>
                            )}

                            {selected.submitted_code && (
                                <div className="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <p className="text-xs font-semibold text-gray-500 uppercase px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                        Ваше решение
                                    </p>
                                    <pre className="p-4 text-xs leading-relaxed bg-slate-900 text-slate-100 font-mono overflow-x-auto max-h-80">
                                        {selected.submitted_code}
                                    </pre>
                                </div>
                            )}

                            {selected.lesson_feedback && (
                                <div className="rounded-xl border border-violet-200 dark:border-violet-800 p-4 bg-violet-50/50 dark:bg-violet-950/20">
                                    <p className="text-xs font-semibold text-violet-700 dark:text-violet-300 uppercase mb-2">
                                        Обратная связь
                                    </p>
                                    <p className="text-sm text-gray-800 dark:text-gray-200">{selected.lesson_feedback}</p>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => onReviewTheory(selected.subtopic_id)}
                                    className="px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-colors"
                                >
                                    Перечитать теорию
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
