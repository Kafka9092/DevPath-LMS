import React from "react";
import { ChatBlock } from "../../types/WorkspaceTypes";
import { LessonProse, renderLessonInline } from "../LessonProse";

interface Props {
    block: ChatBlock;
    onContinue: () => void;
    onBack?: () => void;
    onAskQuestion: () => void;
    canInteract: boolean;
    loading: boolean;
}

export const TheorySlideCard: React.FC<Props> = ({
    block, onContinue, onBack, onAskQuestion, canInteract, loading,
}) => (
    <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl flex flex-col max-h-full overflow-hidden">
        <div className="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">
                {block.review_mode ? "Повтор теории · слайд" : "Теория · слайд"}
                {(block.part ?? 1) > 0 && block.total_parts ? ` ${block.part}/${block.total_parts}` : ""}
            </p>
            <h3 className="text-base font-bold text-gray-900 dark:text-gray-100 mt-0.5 leading-snug">
                {block.slide_title ?? `Часть ${block.part ?? 1}`}
            </h3>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4 min-h-0">
            {block.callout && (
                <div className="rounded-xl border-2 border-orange-400 dark:border-orange-500 bg-white dark:bg-gray-900 px-4 py-3">
                    <p className="text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                        {renderLessonInline(block.callout, "callout")}
                    </p>
                </div>
            )}
            <LessonProse content={block.content ?? ""} collapseLongCode />
        </div>

        {canInteract && !loading && (
            <div className="shrink-0 px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row gap-2">
                {(block.part ?? 1) > 1 && onBack && (
                    <button
                        type="button"
                        onClick={onBack}
                        className="sm:w-auto px-5 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-violet-400 text-sm font-medium rounded-xl transition-colors"
                    >
                        ← Назад
                    </button>
                )}
                <button
                    type="button"
                    onClick={onContinue}
                    className="flex-1 py-3 bg-violet-600 hover:bg-orange-500 text-white text-sm font-semibold rounded-xl transition-colors"
                >
                    {block.has_more
                        ? "Далее →"
                        : block.review_mode
                            ? "К итогам урока →"
                            : "Перейти к практике →"}
                </button>
                <button
                    type="button"
                    onClick={onAskQuestion}
                    className="sm:w-auto px-5 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-violet-400 text-sm font-medium rounded-xl transition-colors"
                >
                    Есть вопрос
                </button>
            </div>
        )}
    </div>
);
