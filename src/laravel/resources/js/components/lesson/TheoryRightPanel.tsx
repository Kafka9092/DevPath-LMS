import React from "react";
import { ChatBlock } from "../../types/WorkspaceTypes";
import { TheoryPlaygroundPanel } from "./TheoryPlaygroundPanel";
import { TheoryTracePanel } from "./TheoryTracePanel";

interface Props {
    block: ChatBlock | null;
    direction: string;
    subtopicTitle: string;
    themeTitle?: string;
}

function resolvePanelMode(block: ChatBlock | null): "playground" | "trace" | "none" {
    if (!block) {
        return "none";
    }

    const mode = block.right_panel_mode;
    if (mode === "playground" && block.playground?.code) {
        return "playground";
    }
    if (mode === "trace" && block.trace_steps && block.trace_steps.length > 0) {
        return "trace";
    }
    if (block.playground?.code) {
        return "playground";
    }

    return "none";
}

export const TheoryRightPanel: React.FC<Props> = ({
    block,
    direction,
    subtopicTitle,
    themeTitle,
}) => {
    const mode = resolvePanelMode(block);
    const title = block?.slide_title ?? subtopicTitle;

    const panelLabel =
        mode === "playground" ? "Пример кода" : mode === "trace" ? "Память" : "Визуализация";

    return (
        <div className="h-full min-h-0 flex flex-col rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
                <p className="text-[10px] font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-widest">
                    {panelLabel}
                </p>
                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5 truncate">{title}</p>
                {themeTitle && <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{themeTitle}</p>}
            </div>

            <div className="flex-1 min-h-0 p-4 overflow-y-auto">
                {!block && (
                    <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-8">
                        Пример кода появится на каждом шаге теории.
                    </p>
                )}

                {block && mode === "playground" && block.playground?.code && (
                    <TheoryPlaygroundPanel
                        playground={block.playground}
                        direction={direction}
                    />
                )}

                {block && mode === "trace" && block.trace_steps && block.trace_steps.length > 0 && (
                    <TheoryTracePanel steps={block.trace_steps} slideTitle={title} />
                )}

                {block && mode === "none" && (
                    <p className="text-sm text-gray-400 dark:text-gray-500 text-center py-8">
                        На этом шаге отдельный пример кода не требуется — смотрите текст слева.
                    </p>
                )}
            </div>
        </div>
    );
};
