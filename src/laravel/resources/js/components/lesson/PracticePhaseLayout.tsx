import React, { useMemo, useRef, useState } from "react";
import { ChatBlock, HintType, Subtopic } from "../../types/WorkspaceTypes";
import { TaskDescriptionCard } from "./TaskDescription";
import { PracticeChatPanel } from "./PracticeChatPanel";
import { TaskEditorPanel } from "./TaskEditorPanel";

interface Props {
    subtopic: Subtopic;
    themeTitle: string;
    taskBlock: ChatBlock;
    blocks: ChatBlock[];
    direction: string;
    loading: boolean;
    feedbackBlock?: ChatBlock;
    nextSubtopic: { id: number; title: string } | null;
    onSendQuestion: (msg: string) => void;
    onRequestTheory: () => void;
    onSubmitSolution: (code: string) => void;
    onNextSubtopic: () => void;
    onRequestHint?: (type: HintType) => void;
    onCodeStruggle?: (kind: "delete_spike" | "rewrite_burst" | "undo_burst") => void;
    helpMessage?: string | null;
    suggestHint?: boolean;
    onDismissHelp?: () => void;
}

export const PracticePhaseLayout: React.FC<Props> = ({
    subtopic,
    themeTitle,
    taskBlock,
    blocks,
    direction,
    loading,
    feedbackBlock,
    nextSubtopic,
    onSendQuestion,
    onRequestTheory,
    onSubmitSolution,
    onNextSubtopic,
    onRequestHint,
    onCodeStruggle,
    helpMessage,
    suggestHint,
    onDismissHelp,
}) => {
    const [question, setQuestion] = useState("");
    const inputRef = useRef<HTMLInputElement>(null);

    const chatItems = useMemo(() => {
        const types = new Set(["mentor", "user_message", "answer", "hint", "system", "solution_feedback"]);
        return blocks.filter(b => types.has(b.type));
    }, [blocks]);

    const sendQuestion = () => {
        if (!question.trim() || loading) return;
        onSendQuestion(question.trim());
        setQuestion("");
    };

    const lessonPassed = feedbackBlock?.is_correct === true;

    return (
        <div className="h-full min-h-0 flex flex-col p-4 gap-0 overflow-hidden">
            <div className="shrink-0 flex items-center justify-between mb-3 px-1">
                <div className="min-w-0">
                    <p className="text-xs text-slate-500 truncate">{themeTitle}</p>
                    <h1 className="text-sm font-bold text-slate-900 truncate">{subtopic.title}</h1>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={onRequestTheory}
                        disabled={loading}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100 disabled:opacity-50 transition-colors"
                    >
                        ← Вернуться к теории
                    </button>
                    <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md bg-orange-50 text-orange-700">
                        Практика
                    </span>
                </div>
            </div>

            <div className="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[2fr_3fr] gap-4 overflow-hidden">
                {}
                <div className="min-h-0 flex flex-col gap-3 overflow-hidden">
                    <TaskDescriptionCard
                        title={taskBlock.title ?? subtopic.title}
                        description={taskBlock.description ?? ""}
                        action={taskBlock.action}
                        functionSignature={taskBlock.function_signature}
                        constraints={taskBlock.constraints}
                    />
                    <PracticeChatPanel
                        items={chatItems}
                        loading={loading}
                        question={question}
                        onQuestionChange={setQuestion}
                        onSend={sendQuestion}
                        inputRef={inputRef}
                    />
                </div>

                {}
                <div className="min-h-[280px] lg:min-h-0 h-full overflow-hidden">
                    <TaskEditorPanel
                        block={taskBlock}
                        direction={direction}
                        loading={loading}
                        onSubmit={onSubmitSolution}
                        onRequestHint={onRequestHint}
                        onCodeStruggle={onCodeStruggle}
                        showHelpOffer={suggestHint}
                        helpMessage={helpMessage}
                        onDismissHelp={onDismissHelp}
                        canContinueLesson={lessonPassed}
                        onContinueLesson={nextSubtopic ? onNextSubtopic : undefined}
                        continueLabel={nextSubtopic ? "Продолжить урок →" : "Урок завершён"}
                    />
                </div>
            </div>
        </div>
    );
};
