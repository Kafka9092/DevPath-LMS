import React, { useEffect, useRef } from "react";
import {
    MapIcon,
    MagnifyingGlassIcon,
    RocketLaunchIcon,
    Squares2X2Icon,
} from "@heroicons/react/24/outline";
import { ChatBlock, MentorActionId, MentorOptionId } from "../../types/WorkspaceTypes";
import { LessonProse } from "../LessonProse";

function formatChatText(text: string): string {
    return text.replace(/\\n/g, "\n");
}

function MenuIcon({ name }: { name?: string }) {
    const cls = "w-4 h-4 shrink-0 text-violet-600";
    switch (name) {
        case "map": return <MapIcon className={cls} />;
        case "magnifier": return <MagnifyingGlassIcon className={cls} />;
        case "rocket": return <RocketLaunchIcon className={cls} />;
        case "squares": return <Squares2X2Icon className={cls} />;
        default: return <MapIcon className={cls} />;
    }
}

function MentorAvatar() {
    return (
        <div className="w-7 h-7 shrink-0 rounded-full bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 text-[10px] font-bold flex items-center justify-center">
            AI
        </div>
    );
}

interface Props {
    items: ChatBlock[];
    loading: boolean;
    question: string;
    onQuestionChange: (v: string) => void;
    onSend: () => void;
    inputRef?: React.RefObject<HTMLInputElement | null>;
    onMentorAction?: (action: MentorActionId) => void;
    onMentorOption?: (optionId: MentorOptionId) => void;
    mentorInteractionLocked?: boolean;
    mentorChatBlocked?: boolean;
}

export const MENTOR_CHAT_BLOCKED_MESSAGE =
    "Вы нарушили правила общения с ментором. На этом уроке ментор не доступен.";

export const PracticeChatPanel: React.FC<Props> = ({
    items,
    loading,
    question,
    onQuestionChange,
    onSend,
    inputRef,
    onMentorAction,
    onMentorOption,
    mentorInteractionLocked,
    mentorChatBlocked,
}) => {
    const scrollRef = useRef<HTMLDivElement>(null);
    const lastPromptId = [...items].reverse().find(i => i.type === "mentor_prompt")?.id;
    const lastMenuId = [...items].reverse().find(i => i.type === "mentor_menu")?.id;

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
    }, [items, loading]);

    return (
        <div className="flex-1 min-h-0 flex flex-col rounded-xl border border-slate-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div className="shrink-0 px-4 py-2.5 border-b border-slate-100 dark:border-gray-800">
                <p className="text-xs font-semibold text-slate-600 dark:text-gray-300">Чат с ментором</p>
            </div>

            <div ref={scrollRef} className="flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-3">
                {items.length === 0 && !loading && (
                    <p className="text-xs text-slate-400 text-center py-6">
                        Спросите ментора, если что-то непонятно в условии
                    </p>
                )}
                {items.map(item => {
                    if (item.type === "user_message") {
                        return (
                            <div key={item.id} className="flex justify-end">
                                <div className="max-w-[92%] bg-violet-600 text-white text-sm leading-relaxed rounded-2xl rounded-br-md px-4 py-2.5 shadow-sm whitespace-pre-wrap">
                                    {item.message}
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "mentor_prompt") {
                        const isLatest = item.id === lastPromptId;
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className="text-sm rounded-2xl rounded-bl-md px-4 py-3 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 max-w-full space-y-3">
                                    <p className="leading-relaxed text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                        {formatChatText(item.message ?? "")}
                                    </p>
                                    {isLatest && item.actions && item.actions.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {item.actions.map(action => (
                                                <button
                                                    key={action.id}
                                                    type="button"
                                                    disabled={loading || mentorInteractionLocked}
                                                    onClick={() => onMentorAction?.(action.id)}
                                                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors disabled:opacity-50 ${
                                                        action.id === "accept"
                                                            ? "bg-violet-600 text-white border-violet-600 hover:bg-violet-700"
                                                            : "bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:border-violet-400"
                                                    }`}
                                                >
                                                    {action.label}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "mentor_menu") {
                        const isLatest = item.id === lastMenuId;
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className="text-sm rounded-2xl rounded-bl-md px-4 py-3 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 max-w-full space-y-3">
                                    <p className="leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                        {formatChatText(item.message ?? "")}
                                    </p>
                                    {isLatest && item.menu_options && (
                                        <div className="flex flex-col gap-2">
                                            {item.menu_options.map(opt => (
                                                <button
                                                    key={opt.id}
                                                    type="button"
                                                    disabled={loading || mentorInteractionLocked}
                                                    onClick={() => onMentorOption?.(opt.id)}
                                                    className="flex items-center gap-2 w-full text-left px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-violet-400 hover:bg-violet-50/50 dark:hover:bg-violet-950/30 text-gray-800 dark:text-gray-200 text-xs font-medium transition-colors disabled:opacity-50"
                                                >
                                                    <MenuIcon name={opt.icon} />
                                                    <span>{opt.label}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "mentor" || item.type === "system") {
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className="bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 text-sm leading-relaxed rounded-2xl rounded-bl-md px-4 py-2.5 border border-gray-200 dark:border-gray-700 whitespace-pre-wrap">
                                    {formatChatText(item.message ?? item.content ?? "")}
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "answer" || item.type === "hint") {
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className="text-sm rounded-2xl rounded-bl-md px-4 py-2.5 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 max-w-full">
                                    {item.title && (
                                        <p className="text-[10px] font-semibold uppercase text-violet-600 mb-1.5">
                                            {item.title}
                                        </p>
                                    )}
                                    <LessonProse content={formatChatText(item.content ?? "")} />
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "micro_task") {
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className="text-sm rounded-2xl rounded-bl-md px-4 py-3 border-2 border-orange-400 dark:border-orange-500 bg-white dark:bg-gray-800 max-w-full">
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">{item.title ?? "Разминка"}</p>
                                    <p className="mt-1.5 leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{item.description}</p>
                                </div>
                            </div>
                        );
                    }
                    if (item.type === "solution_feedback") {
                        const ok = item.is_correct;
                        return (
                            <div key={item.id} className="flex gap-2 items-end max-w-[95%]">
                                <MentorAvatar />
                                <div className={`text-sm rounded-2xl rounded-bl-md px-4 py-3 border max-w-full bg-white dark:bg-gray-800 ${
                                    ok ? "border-violet-200 dark:border-violet-800" : "border-gray-300 dark:border-gray-600"
                                }`}>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">
                                        {ok ? "Решение принято" : "Нужны правки"}
                                        {item.score != null && (
                                            <span className="ml-2 opacity-80 font-normal">{item.score}/100</span>
                                        )}
                                    </p>
                                    {item.feedback && (
                                        <div className="mt-1.5">
                                            <LessonProse content={formatChatText(item.feedback)} />
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    }
                    return null;
                })}
                {loading && (
                    <div className="flex gap-2 items-end max-w-[95%]">
                        <MentorAvatar />
                        <div className="bg-slate-100 dark:bg-gray-800 text-slate-600 dark:text-gray-200 text-sm leading-relaxed rounded-2xl rounded-bl-md px-4 py-3 border border-slate-200 dark:border-gray-600">
                            <span className="inline-flex items-center gap-2">
                                <span className="flex gap-0.5" aria-hidden>
                                    {[0, 1, 2].map(i => (
                                        <span
                                            key={i}
                                            className="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce"
                                            style={{ animationDelay: `${i * 0.12}s` }}
                                        />
                                    ))}
                                </span>
                                Ментор печатает…
                            </span>
                        </div>
                    </div>
                )}
            </div>

            <div className="shrink-0 p-3 border-t border-slate-100 dark:border-gray-800 bg-slate-50/50 dark:bg-gray-900/50">
                {mentorChatBlocked ? (
                    <p className="text-sm text-slate-600 dark:text-gray-400 text-center px-2 py-3 leading-relaxed">
                        {MENTOR_CHAT_BLOCKED_MESSAGE}
                    </p>
                ) : (
                    <div className="flex gap-2 items-center p-1.5 rounded-xl border-2 border-violet-400 dark:border-violet-600 bg-white dark:bg-gray-900">
                        <input
                            ref={inputRef}
                            type="text"
                            value={question}
                            onChange={e => onQuestionChange(e.target.value)}
                            onKeyDown={e => e.key === "Enter" && onSend()}
                            placeholder="Вопрос по задаче..."
                            disabled={loading}
                            className="flex-1 min-w-0 px-2 py-2 bg-transparent text-sm text-slate-800 dark:text-gray-100 placeholder:text-slate-400 dark:placeholder:text-gray-500 focus:outline-none disabled:opacity-50"
                        />
                        <button
                            type="button"
                            onClick={onSend}
                            disabled={!question.trim() || loading}
                            className="shrink-0 px-3 py-2 bg-violet-600 hover:bg-violet-700 disabled:bg-slate-200 disabled:text-slate-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500 text-white text-sm font-semibold rounded-lg transition-colors"
                        >
                            Отправить
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};
