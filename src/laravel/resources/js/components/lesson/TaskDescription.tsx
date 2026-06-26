import React from "react";


function renderEnrichedSegment(text: string, keyPrefix: string): React.ReactNode[] {
    const pattern = /(`[^`]+`|\*\*[^*]+\*\*|(?:npm|yarn|pnpm|docker|php|artisan|npx|cd|git)\s+[\w./-]+|(?:\/|[A-Za-z]:\\)[\w./-]+\.(?:ts|tsx|js|jsx|php|py|json|env|md)|\b[\w-]+\.(?:ts|tsx|js|jsx|php|py)\b)/gi;
    const nodes: React.ReactNode[] = [];
    let last = 0;
    let m: RegExpExecArray | null;
    let i = 0;

    while ((m = pattern.exec(text)) !== null) {
        if (m.index > last) {
            nodes.push(<span key={`${keyPrefix}-t-${i++}`}>{text.slice(last, m.index)}</span>);
        }
        const token = m[0];
        if (token.startsWith("**")) {
            nodes.push(
                <strong key={`${keyPrefix}-b-${i++}`} className="font-semibold text-slate-900 dark:text-gray-100">
                    {token.slice(2, -2)}
                </strong>
            );
        } else {
            const codeText = token.startsWith("`") ? token.slice(1, -1) : token;
            nodes.push(
                <code
                    key={`${keyPrefix}-c-${i++}`}
                    className="mx-0.5 px-1.5 py-0.5 rounded bg-slate-100 dark:bg-gray-800 text-slate-800 dark:text-gray-200 text-[0.9em] font-mono border border-slate-200 dark:border-gray-700"
                >
                    {codeText}
                </code>
            );
        }
        last = m.index + token.length;
    }

    if (last < text.length) {
        nodes.push(<span key={`${keyPrefix}-end`}>{text.slice(last)}</span>);
    }

    return nodes.length ? nodes : [text];
}

interface Props {
    title: string;
    description: string;
    action?: string;
    functionSignature?: string;
    constraints?: string[];
}

export const TaskDescriptionCard: React.FC<Props> = ({
    title,
    description,
    action,
    functionSignature,
    constraints,
}) => {
    return (
        <div className="shrink-0 rounded-xl border border-slate-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-100 dark:border-gray-800 bg-slate-50/80 dark:bg-gray-900/80">
                <p className="text-[10px] font-bold uppercase tracking-widest text-violet-600 dark:text-violet-400">Условие задачи</p>
                <h2 className="mt-1 text-base font-bold text-slate-900 dark:text-gray-100 leading-snug">{title}</h2>
            </div>
            <div className="px-4 py-4 space-y-4 max-h-[min(48vh,420px)] overflow-y-auto">
                {action && (
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-gray-400 mb-1.5">
                            Задание
                        </p>
                        <p className="text-sm font-medium text-slate-900 dark:text-gray-100 leading-relaxed">
                            {renderEnrichedSegment(action, "action")}
                        </p>
                    </div>
                )}

                {functionSignature && (
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-gray-400 mb-1.5">
                            Объявление функции
                        </p>
                        <pre className="text-xs font-mono bg-white dark:bg-gray-900 text-slate-900 dark:text-gray-100 rounded-lg px-3 py-2 overflow-x-auto border-2 border-orange-400 dark:border-orange-500">
                            {functionSignature}
                        </pre>
                    </div>
                )}

                {description.trim() && (
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-gray-400 mb-1.5">
                            Контекст
                        </p>
                        <div className="text-sm leading-relaxed text-slate-700 dark:text-gray-300 space-y-2">
                            {description.split(/\n\n+/).map((para, idx) => (
                                <p key={idx}>{renderEnrichedSegment(para.trim(), `p-${idx}`)}</p>
                            ))}
                        </div>
                    </div>
                )}

                {constraints && constraints.length > 0 && (
                    <div className="rounded-lg border border-violet-100 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/30 px-3 py-3">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-violet-700 dark:text-violet-300 mb-2">
                            Ограничения и правила
                        </p>
                        <ul className="text-sm text-violet-900 dark:text-violet-200 space-y-1.5">
                            {constraints.map((c, i) => (
                                <li key={i} className="flex gap-2">
                                    <span className="text-violet-500 shrink-0 font-bold">—</span>
                                    <span>{renderEnrichedSegment(c, `c-${i}`)}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
};
