import React, { useEffect, useRef, useState } from "react";
import { ThemedMonacoEditor } from "@/components/ThemedMonacoEditor";
import { TheoryPlayground } from "../../types/WorkspaceTypes";
import { monacoLanguage } from "../../lib/monacoLanguage";

interface Props {
    playground: TheoryPlayground;
    direction: string;
}

export const TheoryPlaygroundPanel: React.FC<Props> = ({ playground, direction }) => {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [height, setHeight] = useState(160);

    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return;
        const update = () => setHeight(Math.max(120, Math.min(280, el.clientHeight)));
        update();
        const ro = new ResizeObserver(update);
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const lang = playground.language || monacoLanguage(direction);

    return (
        <div className="h-full min-h-0 flex flex-col gap-2">
            <div ref={wrapRef} className="flex-1 min-h-[140px] rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-black">
                <ThemedMonacoEditor
                    height={height}
                    language={monacoLanguage(lang)}
                    value={playground.code}
                    options={{
                        readOnly: true,
                        minimap: { enabled: false },
                        fontSize: 13,
                        lineHeight: 20,
                        scrollBeyondLastLine: false,
                        automaticLayout: true,
                    }}
                />
            </div>
            {playground.stdin?.trim() && (
                <div className="shrink-0 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-3 py-2">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                        Входные данные
                    </p>
                    <pre className="text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap font-mono">
                        {playground.stdin}
                    </pre>
                </div>
            )}
        </div>
    );
};
