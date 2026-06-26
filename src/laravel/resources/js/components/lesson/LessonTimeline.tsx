import React, { useEffect, useRef } from "react";
import { ChatBlock } from "../../types/WorkspaceTypes";
import { LessonProse } from "../LessonProse";

interface Props {
    items: ChatBlock[];
    loading: boolean;
}

export const LessonTimeline: React.FC<Props> = ({ items, loading }) => {
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: "smooth" });
    }, [items, loading]);

    if (items.length === 0 && !loading) {
        return (
            <p className="text-xs text-slate-400 text-center py-4">Диалог с ментором появится здесь</p>
        );
    }

    return (
        <div className="space-y-3 max-h-48 overflow-y-auto pr-1">
            {items.map(item => {
                if (item.type === "user_message") {
                    return (
                        <div key={item.id} className="flex justify-end">
                            <div className="bg-violet-600 text-white text-sm rounded-2xl rounded-tr-md px-4 py-2 max-w-[90%]">
                                {item.message}
                            </div>
                        </div>
                    );
                }
                if (item.type === "mentor" || item.type === "system") {
                    return (
                        <div key={item.id} className="flex gap-2.5">
                            <div className="w-8 h-8 shrink-0 rounded-full bg-violet-100 dark:bg-violet-950 flex items-center justify-center text-violet-700 dark:text-violet-300 text-xs font-bold">
                                AI
                            </div>
                            <div className="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl rounded-tl-md px-4 py-2.5 text-sm text-slate-700 dark:text-gray-200 max-w-[90%] shadow-sm">
                                {item.message ?? item.content}
                            </div>
                        </div>
                    );
                }
                if (item.type === "answer") {
                    return (
                        <div key={item.id} className="flex gap-2.5">
                            <div className="w-8 h-8 shrink-0 rounded-full bg-violet-100 dark:bg-violet-950 flex items-center justify-center text-violet-700 dark:text-violet-300 text-xs font-bold">
                                AI
                            </div>
                            <div className="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl rounded-tl-md px-4 py-3 max-w-[95%] shadow-sm">
                                <LessonProse content={item.content ?? ""} />
                            </div>
                        </div>
                    );
                }
                if (item.type === "hint") {
                    return (
                        <div key={item.id} className="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl px-4 py-3 text-sm">
                            <p className="text-xs font-bold text-amber-800 dark:text-amber-200 mb-1">{item.title ?? "Подсказка"}</p>
                            <LessonProse content={item.content ?? ""} />
                        </div>
                    );
                }
                return null;
            })}
            {loading && (
                <div className="flex gap-2 items-center text-sm text-violet-700 dark:text-violet-300">
                    <span className="flex gap-1">
                        {[0, 1, 2].map(i => (
                            <span key={i} className="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce" style={{ animationDelay: `${i * 0.1}s` }} />
                        ))}
                    </span>
                    Ментор думает...
                </div>
            )}
            <div ref={endRef} />
        </div>
    );
};
