import React, { useEffect, useMemo, useRef, useState } from "react";
import { ChatBubbleLeftRightIcon } from "@heroicons/react/24/outline";
import { ChatBlock, LessonPhase, Subtopic } from "../../types/WorkspaceTypes";
import { TheorySlideCard } from "./TheorySlideCard";
import { TaskDescriptionCard } from "./TaskDescription";
import { PracticeChatPanel } from "./PracticeChatPanel";
import { TaskEditorPanel } from "./TaskEditorPanel";
import { TheoryRightPanel } from "./TheoryRightPanel";
import { LessonProgressBar } from "./LessonProgressBar";

const CHAT_KEY = "devpath-show-chat";
const CHAT_SIZE_KEY = "devpath-chat-size-pct";
const PANEL_ICON_BOTTOM_PCT = 24;

function readSizePct(key: string, fallback: number): number {
    const raw = localStorage.getItem(key);
    const n = raw ? Number(raw) : fallback;
    if (!Number.isFinite(n)) return fallback;
    return Math.min(72, Math.max(18, n));
}

interface PurpleSideRailProps {
    side: "left" | "right";
    active?: boolean;
    anchorBottomPct: number;
    onIconClick?: () => void;
    icon?: React.ReactNode;
    title?: string;
    badge?: number;
}

function PurpleSideRail({
    side,
    active = false,
    anchorBottomPct,
    onIconClick,
    icon,
    title,
    badge,
}: PurpleSideRailProps) {
    return (
        <div
            className={`relative w-9 shrink-0 ${
                side === "left" ? "border-r border-violet-700/40" : "border-l border-violet-700/40"
            } ${active ? "bg-violet-700" : "bg-violet-600"}`}
            aria-hidden={!icon}
        >
            {icon && onIconClick && title && (
                <button
                    type="button"
                    onClick={onIconClick}
                    title={title}
                    aria-label={title}
                    aria-expanded={active}
                    className="absolute left-1/2 -translate-x-1/2 z-10 w-9 h-9 flex items-center justify-center text-white rounded-lg hover:bg-violet-800/80 transition-colors"
                    style={{ bottom: `calc(${anchorBottomPct}% - 18px)` }}
                >
                    <div className="relative flex items-center justify-center">
                        {badge != null && badge > 0 && (
                            <span
                                className={`absolute min-w-[16px] h-4 px-1 flex items-center justify-center rounded bg-white text-violet-800 text-[10px] font-semibold leading-none shadow border border-violet-200/90 ${
                                    side === "left" ? "-top-1.5 -right-2" : "-top-1.5 -left-2"
                                }`}
                            >
                                {badge > 99 ? "99+" : badge}
                            </span>
                        )}
                        {icon}
                    </div>
                </button>
            )}
        </div>
    );
}

function ResizeHandle(props: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            role="separator"
            className="shrink-0 h-2 my-0.5 cursor-ns-resize flex items-center justify-center group touch-none"
            {...props}
        >
            <div className="w-12 h-1 rounded-full bg-violet-200 group-hover:bg-violet-500 transition-colors" />
        </div>
    );
}

export interface WorkspaceLessonLayoutProps {
    subtopic: Subtopic;
    themeTitle: string;
    direction: string;
    phase: LessonPhase;
    blocks: ChatBlock[];
    loading: boolean;
    nextSubtopic: { id: number; title: string } | null;
    submitLoading: boolean;
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
}

export const WorkspaceLessonLayout: React.FC<WorkspaceLessonLayoutProps> = ({
    subtopic,
    themeTitle,
    direction,
    phase,
    blocks,
    loading,
    nextSubtopic,
    submitLoading,
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
}) => {
    const isPractice = phase === "task" || phase === "evaluating" || phase === "completed";

    const [showChat, setShowChat] = useState(() => localStorage.getItem(CHAT_KEY) !== "0");
    const [question, setQuestion] = useState("");
    const [chatSizePct, setChatSizePct] = useState(() => readSizePct(CHAT_SIZE_KEY, 38));
    const [seenMentorCount, setSeenMentorCount] = useState(0);
    const leftColumnRef = useRef<HTMLDivElement>(null);
    const chatDragRef = useRef<{ startY: number; startPct: number } | null>(null);

    useEffect(() => {
        localStorage.setItem(CHAT_KEY, showChat ? "1" : "0");
    }, [showChat]);

    useEffect(() => {
        localStorage.setItem(CHAT_SIZE_KEY, String(chatSizePct));
    }, [chatSizePct]);

    useEffect(() => {
        if (chatOpenSignal && chatOpenSignal > 0) {
            setShowChat(true);
        }
    }, [chatOpenSignal]);

    const currentTheory = [...blocks].reverse().find(b => b.type === "theory") ?? null;
    const taskBlock = [...blocks].reverse().find(b => b.type === "task") ?? null;
    const feedbackBlock = blocks.find(b => b.type === "solution_feedback");

    const chatItems = blocks.filter(b =>
        ["mentor", "user_message", "answer", "hint", "system", "solution_feedback", "micro_task", "mentor_prompt", "mentor_menu"].includes(b.type)
    );

    const mentorMessageCount = useMemo(
        () => chatItems.filter(b =>
            ["mentor", "system", "mentor_prompt", "mentor_menu", "hint", "answer"].includes(b.type)
        ).length,
        [chatItems],
    );

    const unreadCount = showChat ? 0 : Math.max(0, mentorMessageCount - seenMentorCount);

    useEffect(() => {
        if (showChat) {
            setSeenMentorCount(mentorMessageCount);
        }
    }, [showChat, mentorMessageCount]);

    const chatResize = {
        onPointerDown: (e: React.PointerEvent) => {
            e.preventDefault();
            chatDragRef.current = { startY: e.clientY, startPct: chatSizePct };
            (e.target as HTMLElement).setPointerCapture(e.pointerId);
        },
        onPointerMove: (e: React.PointerEvent) => {
            if (!chatDragRef.current || !leftColumnRef.current) return;
            const h = leftColumnRef.current.getBoundingClientRect().height;
            if (h < 80) return;
            const deltaPct = ((chatDragRef.current.startY - e.clientY) / h) * 100;
            setChatSizePct(Math.min(72, Math.max(18, chatDragRef.current.startPct + deltaPct)));
        },
        onPointerUp: () => {
            chatDragRef.current = null;
        },
    };

    const step = currentTheory?.part ?? 0;
    const total = currentTheory?.total_parts ?? 0;
    const stepLabel = currentTheory?.slide_title ?? subtopic.title;
    const lessonPassed = feedbackBlock?.is_correct === true;

    const sendQuestion = () => {
        if (!question.trim() || loading || mentorChatBlocked) return;
        onSendQuestion(question.trim());
        setQuestion("");
    };

    const lessonPanel = isPractice && taskBlock ? (
        <div className="min-h-0 flex flex-col gap-3 overflow-y-auto">
            {taskBlock.intro_message && (
                <div className="shrink-0 rounded-xl border-2 border-orange-300 dark:border-orange-600 bg-orange-50 dark:bg-orange-950/30 px-4 py-3">
                    <p className="text-sm leading-relaxed text-orange-950 dark:text-orange-100">
                        {taskBlock.intro_message}
                    </p>
                </div>
            )}
            <TaskDescriptionCard
                title={taskBlock.title ?? subtopic.title}
                description={taskBlock.description ?? ""}
                action={taskBlock.action}
                functionSignature={taskBlock.function_signature}
                constraints={taskBlock.constraints}
            />
        </div>
    ) : currentTheory ? (
        <TheorySlideCard
            block={currentTheory}
            onContinue={onContinue}
            onBack={onTheoryBack}
            onAskQuestion={() => setShowChat(true)}
            canInteract={!loading && phase === "learning"}
            loading={loading}
        />
    ) : null;

    const codePanel = isPractice && taskBlock ? (
        <TaskEditorPanel
            block={taskBlock}
            direction={direction}
            editorCode={editorCode}
            submitLoading={submitLoading}
            onSubmit={onSubmitSolution}
            onCodeChange={onCodeChange}
            onCodeActivity={onCodeActivity}
            canContinueLesson={lessonPassed}
            onContinueLesson={nextSubtopic ? onNextSubtopic : undefined}
            continueLabel={nextSubtopic ? "Следующий урок →" : "Урок завершён"}
        />
    ) : null;

    const visualizerPanel = !isPractice ? (
        <TheoryRightPanel
            block={currentTheory}
            direction={direction}
            subtopicTitle={subtopic.title}
            themeTitle={themeTitle}
        />
    ) : null;

    return (
        <div className="h-full min-h-0 flex flex-col overflow-hidden bg-gray-50 dark:bg-gray-950">
            <header className="shrink-0 flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                <div className="min-w-0">
                    <p className="text-xs text-gray-400 truncate">{themeTitle}</p>
                    <h2 className="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{subtopic.title}</h2>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    {isPractice && (
                        <button
                            type="button"
                            onClick={onRequestTheory}
                            disabled={loading}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800 rounded-lg hover:bg-violet-100 dark:hover:bg-violet-950/60 disabled:opacity-50 transition-colors"
                        >
                            ← Вернуться к теории
                        </button>
                    )}
                    <span className={`text-[10px] font-semibold uppercase tracking-wider px-2 py-1 rounded-md border shrink-0 ${
                        isPractice
                            ? "border-orange-500 text-orange-600 bg-orange-50 dark:bg-orange-950/30"
                            : currentTheory?.review_mode
                                ? "border-violet-400 text-violet-600 bg-violet-50 dark:bg-violet-950/30"
                                : "border-violet-500 text-violet-600 bg-violet-50 dark:bg-violet-950/30"
                    }`}>
                        {isPractice ? "Практика" : currentTheory?.review_mode ? "Повтор теории" : "Теория"}
                    </span>
                </div>
            </header>

            {(phase === "learning" || currentTheory?.review_mode) && currentTheory && (
                <div className="shrink-0 px-4 py-2 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
                    <LessonProgressBar step={step} total={total} label={stepLabel} />
                </div>
            )}

            <div className="flex-1 min-h-0 flex overflow-hidden">
                <PurpleSideRail
                    side="left"
                    active={showChat}
                    anchorBottomPct={PANEL_ICON_BOTTOM_PCT}
                    onIconClick={() => setShowChat(v => !v)}
                    icon={<ChatBubbleLeftRightIcon className="w-5 h-5" />}
                    title="Чат с ментором"
                    badge={unreadCount}
                />

                <div className="flex-1 min-h-0 flex p-3 gap-3 overflow-hidden min-w-0">
                    <div ref={leftColumnRef} className="min-h-0 flex flex-col min-w-0 flex-1">
                        <div
                            className="min-h-0 flex flex-col overflow-hidden"
                            style={showChat ? { flex: `${100 - chatSizePct} 1 0`, minHeight: 100 } : { flex: "1 1 0" }}
                        >
                            <p className="shrink-0 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                                Урок
                            </p>
                            <div className="flex-1 min-h-0">{lessonPanel}</div>
                        </div>
                        {showChat && (
                            <>
                                <ResizeHandle
                                    aria-label="Изменить высоту чата"
                                    onPointerDown={chatResize.onPointerDown}
                                    onPointerMove={chatResize.onPointerMove}
                                    onPointerUp={chatResize.onPointerUp}
                                    onPointerCancel={chatResize.onPointerUp}
                                />
                                <div
                                    className="min-h-0 flex flex-col overflow-hidden"
                                    style={{ flex: `${chatSizePct} 1 0`, minHeight: 140 }}
                                >
                                    <PracticeChatPanel
                                        items={chatItems}
                                        loading={loading || submitLoading}
                                        question={question}
                                        onQuestionChange={setQuestion}
                                        onSend={sendQuestion}
                                        onMentorAction={onMentorAction}
                                        onMentorOption={onMentorOption}
                                        mentorInteractionLocked={mentorInteractionLocked}
                                        mentorChatBlocked={mentorChatBlocked}
                                    />
                                </div>
                            </>
                        )}
                    </div>

                    <div className="w-px bg-gray-200 dark:bg-gray-800 shrink-0" />

                    <div className="min-h-0 flex flex-col min-w-0 flex-1">
                        {!isPractice ? (
                            <div className="flex-1 min-h-0 flex flex-col">
                                <p className="shrink-0 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                                    Визуализация
                                </p>
                                <div className="flex-1 min-h-0">{visualizerPanel}</div>
                            </div>
                        ) : (
                            <div className="flex-1 min-h-0 flex flex-col">
                                <p className="shrink-0 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                                    Код
                                </p>
                                <div className="flex-1 min-h-0">{codePanel}</div>
                            </div>
                        )}
                    </div>
                </div>

                <PurpleSideRail side="right" anchorBottomPct={50} />
            </div>
        </div>
    );
};
