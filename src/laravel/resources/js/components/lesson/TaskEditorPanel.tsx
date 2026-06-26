import React, { useEffect, useRef, useState } from "react";
import { ThemedMonacoEditor } from "@/components/ThemedMonacoEditor";
import { ChatBlock } from "../../types/WorkspaceTypes";
import { monacoLanguage } from "../../lib/monacoLanguage";

interface Props {
    block: ChatBlock;
    direction: string;
    editorCode?: string;
    submitLoading: boolean;
    onSubmit: (code: string) => void;
    onCodeChange?: (code: string) => void;
    onCodeActivity?: (code: string) => void;
    canContinueLesson?: boolean;
    onContinueLesson?: () => void;
    continueLabel?: string;
}

export const TaskEditorPanel: React.FC<Props> = ({
    block,
    direction,
    editorCode,
    submitLoading,
    onSubmit,
    onCodeChange,
    onCodeActivity,
    canContinueLesson,
    onContinueLesson,
    continueLabel = "Продолжить урок",
}) => {
    const [code, setCode] = useState(editorCode ?? block.starter_code ?? "");

    useEffect(() => {
        setCode(editorCode ?? block.starter_code ?? "");
    }, [block.id]);
    const editorWrapRef = useRef<HTMLDivElement>(null);
    const [editorHeight, setEditorHeight] = useState(320);

    useEffect(() => {
        onCodeChange?.(code);
    }, [code, onCodeChange]);

    useEffect(() => {
        const el = editorWrapRef.current;
        if (!el) return;

        const update = () => setEditorHeight(Math.max(200, el.clientHeight));
        update();
        const ro = new ResizeObserver(update);
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const handleChange = (value: string | undefined) => {
        const next = value ?? "";
        setCode(next);
        onCodeActivity?.(next);
    };

    return (
        <div className="h-full min-h-0 flex flex-col bg-white dark:bg-gray-950 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
            <div className="shrink-0 flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">solution.{direction.toLowerCase()}</span>
            </div>

            <div ref={editorWrapRef} className="flex-1 min-h-0 bg-white dark:bg-black">
                <ThemedMonacoEditor
                    height={editorHeight}
                    language={monacoLanguage(direction)}
                    value={code}
                    onChange={handleChange}
                    options={{
                        minimap: { enabled: false },
                        fontSize: 14,
                        lineHeight: 22,
                        padding: { top: 12, bottom: 12 },
                        scrollBeyondLastLine: false,
                        automaticLayout: true,
                    }}
                />
            </div>

            <div className="shrink-0 flex flex-wrap items-center gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                <button
                    type="button"
                    onClick={() => onSubmit(code)}
                    disabled={submitLoading || !code.trim()}
                    className="inline-flex items-center gap-2 px-4 py-2.5 bg-violet-600 hover:bg-violet-700 disabled:bg-gray-300 disabled:text-gray-500 dark:disabled:bg-gray-800 dark:disabled:text-gray-500 text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    {submitLoading ? "Проверка…" : "Отправить на проверку"}
                </button>
                {canContinueLesson && onContinueLesson && (
                    <button
                        type="button"
                        onClick={onContinueLesson}
                        className="ml-auto px-4 py-2.5 text-sm font-semibold text-violet-700 dark:text-violet-300 border border-violet-300 dark:border-violet-700 rounded-lg hover:bg-violet-50 dark:hover:bg-violet-950/40 transition-colors"
                    >
                        {continueLabel}
                    </button>
                )}
            </div>
        </div>
    );
};
