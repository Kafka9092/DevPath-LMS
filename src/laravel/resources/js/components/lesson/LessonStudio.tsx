import React from "react";
import { ChatBlock, LessonPhase, Subtopic } from "../../types/WorkspaceTypes";
import { WorkspaceLessonLayout } from "./WorkspaceLessonLayout";
import { LessonCompletedReview } from "./LessonCompletedReview";

export interface LessonStatus {
    generated: boolean;
    completed: boolean;
    theory_complete: boolean;
    theory_part_index: number;
    in_progress: boolean;
    in_progress_subtopic_id?: number | null;
    draft_code?: string | null;
    theory_available?: boolean;
    mentor_chat_blocked?: boolean;
    status?: "generating_content" | "ready" | "failed";
    job_id?: string;
    message?: string;
}

export interface LessonStudioProps {
    subtopic: Subtopic | null;
    themeTitle: string;
    direction: string;
    phase: LessonPhase;
    blocks: ChatBlock[];
    loading: boolean;
    lessonStatus: LessonStatus | null;
    nextSubtopic: { id: number; title: string } | null;
    submitLoading: boolean;
    onBegin: () => void;
    onContinue: () => void;
    onTheoryBack: () => void;
    onSendQuestion: (msg: string) => void;
    onRequestTheory: () => void;
    onSubmitSolution: (code: string) => void;
    onNextSubtopic: () => void;
    onCodeChange?: (code: string) => void;
    onCodeActivity?: (code: string) => void;
    editorCode?: string;
    chatOpenSignal?: number;
    onMentorAction?: (action: import("../../types/WorkspaceTypes").MentorActionId) => void;
    onMentorOption?: (optionId: import("../../types/WorkspaceTypes").MentorOptionId) => void;
    mentorInteractionLocked?: boolean;
    mentorChatBlocked?: boolean;
    onReviewTheory?: () => void;
    onShowLessonSummary?: () => void;
    onReviewContinue?: () => void;
    onReviewBack?: () => void;
}

export const LessonStudio: React.FC<LessonStudioProps> = ({
    subtopic,
    themeTitle,
    direction,
    phase,
    blocks,
    loading,
    lessonStatus,
    nextSubtopic,
    submitLoading,
    onBegin,
    onContinue,
    onTheoryBack,
    onSendQuestion,
    onRequestTheory,
    onSubmitSolution,
    onNextSubtopic,
    onCodeChange,
    onCodeActivity,
    editorCode,
    chatOpenSignal,
    onMentorAction,
    onMentorOption,
    mentorInteractionLocked,
    mentorChatBlocked,
    onReviewTheory,
    onShowLessonSummary,
    onReviewContinue,
    onReviewBack,
}) => {
    const idleErrorBlock = blocks.find(
        b => b.type === "error"
            && !String(b.content).includes("Сначала нажмите")
            && !String(b.content).includes("Урок ещё не готов"),
    );

    if (!subtopic) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-4 text-center px-8">
                <p className="font-semibold text-gray-700 dark:text-gray-300">Выберите тему в плане курса</p>
                <p className="text-sm text-gray-400">Нажмите «План курса» вверху справа</p>
            </div>
        );
    }

    const isGenerating =
        phase === "generating" || lessonStatus?.status === "generating_content";

    if (isGenerating) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-6 px-8 text-center">
                <div className="w-12 h-12 rounded-full border-4 border-violet-200 border-t-violet-600 animate-spin" />
                <div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">Генерируем урок</h2>
                    <p className="text-sm text-gray-500 mt-2 max-w-sm">
                        {subtopic.title} — готовим все слайды теории и практическую задачу. Это займёт 30–90 секунд.
                    </p>
                </div>
            </div>
        );
    }

    if (phase === "idle") {
        const inProgress = lessonStatus?.in_progress ?? false;
        const completed = lessonStatus?.completed ?? false;
        const theoryAvailable = lessonStatus?.theory_available ?? false;

        return (
            <div className="flex flex-col items-center justify-center h-full gap-5 px-8 text-center">
                <div className="w-14 h-14 bg-violet-100 dark:bg-violet-950/50 rounded-2xl flex items-center justify-center">
                    <span className="text-violet-600 font-bold text-lg">AI</span>
                </div>
                <div>
                    <p className="text-xs text-gray-400">{themeTitle}</p>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">{subtopic.title}</h2>
                    <p className="text-sm text-gray-500 mt-2 max-w-md">
                        {completed
                            ? "Урок завершён. Можно перечитать теорию или посмотреть своё решение."
                            : inProgress
                                ? "Продолжите с того места, где остановились."
                                : "Нажмите «Начать урок» — AI подготовит материал, затем откроется первый слайд теории."}
                    </p>
                </div>

                <div className="flex flex-col sm:flex-row gap-2 w-full max-w-md justify-center">
                    {completed ? (
                        <>
                            {theoryAvailable && onReviewTheory && (
                                <button
                                    type="button"
                                    onClick={onReviewTheory}
                                    disabled={loading}
                                    className="flex-1 px-6 py-3 bg-violet-600 hover:bg-violet-700 disabled:bg-gray-200 text-white font-semibold rounded-xl"
                                >
                                    Перечитать теорию
                                </button>
                            )}
                            {onShowLessonSummary && (
                                <button
                                    type="button"
                                    onClick={onShowLessonSummary}
                                    disabled={loading}
                                    className="flex-1 px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:border-violet-400 font-semibold rounded-xl"
                                >
                                    Итоги и решение
                                </button>
                            )}
                        </>
                    ) : (
                        <button
                            type="button"
                            onClick={onBegin}
                            disabled={loading}
                            className="px-8 py-3 bg-violet-600 hover:bg-orange-500 disabled:bg-gray-200 text-white font-semibold rounded-xl"
                        >
                            {inProgress ? "Продолжить урок" : "Начать урок"}
                        </button>
                    )}
                </div>

                {idleErrorBlock && (
                    <p className="text-sm text-rose-600 max-w-md">{idleErrorBlock.content}</p>
                )}
            </div>
        );
    }

    if (phase === "lesson_review") {
        const reviewBlock = blocks.find(b => b.type === "lesson_review");
        if (reviewBlock) {
            return (
                <LessonCompletedReview
                    block={reviewBlock}
                    onReviewTheory={lessonStatus?.theory_available ? onReviewTheory : undefined}
                />
            );
        }
    }

    const layoutPhase = phase === "theory_review" ? "learning" : phase;

    return (
        <WorkspaceLessonLayout
            subtopic={subtopic}
            themeTitle={themeTitle}
            direction={direction}
            phase={layoutPhase}
            blocks={blocks}
            loading={loading}
            nextSubtopic={nextSubtopic}
            submitLoading={submitLoading}
            onContinue={phase === "theory_review" ? (onReviewContinue ?? onContinue) : onContinue}
            onTheoryBack={phase === "theory_review" ? (onReviewBack ?? onTheoryBack) : onTheoryBack}
            onSendQuestion={onSendQuestion}
            onRequestTheory={onRequestTheory}
            onSubmitSolution={onSubmitSolution}
            onNextSubtopic={onNextSubtopic}
            onCodeChange={onCodeChange}
            onCodeActivity={onCodeActivity}
            editorCode={editorCode}
            chatOpenSignal={chatOpenSignal}
            onMentorAction={onMentorAction}
            onMentorOption={onMentorOption}
            mentorInteractionLocked={mentorInteractionLocked}
            mentorChatBlocked={mentorChatBlocked}
        />
    );
};
