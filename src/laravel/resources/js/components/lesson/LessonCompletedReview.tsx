import React from "react";
import { ChatBlock } from "../../types/WorkspaceTypes";

interface Props {
    block: ChatBlock;
    onReviewTheory?: () => void;
}

export const LessonCompletedReview: React.FC<Props> = ({ block, onReviewTheory }) => (
    <div className="h-full min-h-0 overflow-y-auto p-6">
        <div className="max-w-3xl mx-auto space-y-5">
            <div>
                <p className="text-xs font-semibold text-violet-600 uppercase tracking-wider">Урок завершён</p>
                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                    {block.task_title ?? "Практика"}
                </h2>
                {block.score != null && (
                    <p className="text-sm text-gray-500 mt-1">Оценка: {block.score} / 100</p>
                )}
            </div>

            {block.task_description && (
                <div className="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Условие задачи</p>
                    <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">
                        {block.task_description}
                    </p>
                </div>
            )}

            {block.submitted_code && (
                <div className="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <p className="text-xs font-semibold text-gray-500 uppercase px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b">
                        Ваше решение
                    </p>
                    <pre className="p-4 text-xs bg-slate-900 text-slate-100 font-mono overflow-x-auto max-h-96">
                        {block.submitted_code}
                    </pre>
                </div>
            )}

            {block.feedback && (
                <p className="text-sm text-violet-800 dark:text-violet-200 border-l-4 border-violet-400 pl-4">
                    {block.feedback}
                </p>
            )}

            {onReviewTheory && (
                <button
                    type="button"
                    onClick={onReviewTheory}
                    className="px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl"
                >
                    Перечитать теорию
                </button>
            )}
        </div>
    </div>
);
