import React, { useState } from "react";


function stripEmojis(text: string): string {
    return text
        .replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE00}-\u{FE0F}]/gu, "")
        .replace(/[📌✅❌⚠️🔥💡👉📎🎯✨⭐🚀]/g, "");
}

export function renderLessonInline(text: string, keyPrefix: string): React.ReactNode[] {
    const nodes: React.ReactNode[] = [];
    const re = /(\*\*[^*]+\*\*|`[^`]+`)/g;
    let last = 0;
    let m: RegExpExecArray | null;
    let i = 0;

    while ((m = re.exec(text)) !== null) {
        if (m.index > last) {
            nodes.push(<span key={`${keyPrefix}-t-${i++}`}>{text.slice(last, m.index)}</span>);
        }
        const token = m[0];
        if (token.startsWith("**")) {
            nodes.push(
                <span key={`${keyPrefix}-b-${i++}`} className="font-semibold text-violet-700 dark:text-violet-300">
                    {token.slice(2, -2)}
                </span>
            );
        } else {
            nodes.push(
                <code
                    key={`${keyPrefix}-c-${i++}`}
                    className="px-1.5 py-0.5 rounded-md bg-violet-50 dark:bg-violet-950/50 text-violet-900 dark:text-violet-200 text-[0.85em] font-mono border border-violet-100 dark:border-violet-800"
                >
                    {token.slice(1, -1)}
                </code>
            );
        }
        last = m.index + token.length;
    }

    if (last < text.length) {
        nodes.push(<span key={`${keyPrefix}-t-${i}`}>{text.slice(last)}</span>);
    }

    return nodes.length ? nodes : [text];
}

const CollapsibleCode: React.FC<{ code: string; lang: string; collapsed: boolean }> = ({ code, lang, collapsed }) => {
    const [open, setOpen] = useState(!collapsed);
    const lines = code.split("\n").length;
    const label = lang ? `Пример кода (${lang})` : "Пример кода";

    if (!collapsed || lines <= 6) {
        return (
            <pre className="my-4 rounded-xl overflow-hidden border border-slate-800 bg-slate-900">
                {lang && (
                    <div className="px-4 py-1.5 text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-800">
                        {lang}
                    </div>
                )}
                <code className="block p-4 text-[13px] leading-relaxed text-slate-100 font-mono overflow-x-auto whitespace-pre">
                    {code}
                </code>
            </pre>
        );
    }

    return (
        <div className="my-4 rounded-xl border border-slate-200 dark:border-gray-700 overflow-hidden">
            <button
                type="button"
                onClick={() => setOpen(v => !v)}
                className="w-full flex items-center justify-between px-4 py-2.5 bg-slate-50 dark:bg-gray-800 hover:bg-slate-100 dark:hover:bg-gray-700 text-sm font-medium text-violet-700 dark:text-violet-300 transition-colors"
            >
                <span>{open ? "Скрыть" : "Показать"} {label}</span>
                <span className="text-slate-400 dark:text-gray-500 text-xs">{lines} строк</span>
            </button>
            {open && (
                <pre className="border-t border-slate-800 bg-slate-900">
                    <code className="block p-4 text-[13px] leading-relaxed text-slate-100 font-mono overflow-x-auto whitespace-pre">
                        {code}
                    </code>
                </pre>
            )}
        </div>
    );
};

function renderCodeBlock(code: string, lang: string, key: string, collapseLongCode: boolean) {
    return <CollapsibleCode key={key} code={code} lang={lang} collapsed={collapseLongCode} />;
}

export function LessonProse({ content, collapseLongCode = false }: { content: string; collapseLongCode?: boolean }) {
    const cleaned = stripEmojis(content.trim());
    const segments = cleaned.split(/(```[\s\S]*?```)/g);

    const elements: React.ReactNode[] = [];
    let key = 0;

    for (const segment of segments) {
        if (segment.startsWith("```")) {
            const body = segment.slice(3);
            const nl = body.indexOf("\n");
            const lang = nl >= 0 ? body.slice(0, nl).trim() : "";
            const code = (nl >= 0 ? body.slice(nl + 1) : body).replace(/```$/, "").trimEnd();
            elements.push(renderCodeBlock(code, lang, `code-${key++}`, collapseLongCode));
            continue;
        }

        const lines = segment.split("\n");
        let paragraph: string[] = [];
        let listItems: string[] = [];

        const flushParagraph = () => {
            if (paragraph.length === 0) return;
            const text = paragraph.join(" ").trim();
            if (text) {
                elements.push(
                    <p key={`p-${key++}`} className="text-[15px] leading-[1.7] text-slate-700 dark:text-gray-200 mb-3 last:mb-0">
                        {renderLessonInline(text, `p-${key}`)}
                    </p>
                );
            }
            paragraph = [];
        };

        const flushList = () => {
            if (listItems.length === 0) return;
            elements.push(
                <ul key={`ul-${key++}`} className="mb-4 space-y-2 pl-1">
                    {listItems.map((item, idx) => (
                        <li key={idx} className="flex gap-2 text-[15px] leading-relaxed text-slate-700 dark:text-gray-200">
                            <span className="mt-2 w-1.5 h-1.5 shrink-0 rounded-full bg-violet-500" />
                            <span>{renderLessonInline(item, `li-${key}-${idx}`)}</span>
                        </li>
                    ))}
                </ul>
            );
            listItems = [];
        };

        for (const rawLine of lines) {
            const line = rawLine.trimEnd();
            const trimmed = line.trim();

            if (trimmed === "") {
                flushList();
                flushParagraph();
                continue;
            }

            const heading = trimmed.match(/^#{1,4}\s+(.+)$/);
            if (heading) {
                flushList();
                flushParagraph();
                const level = trimmed.match(/^(#+)/)?.[1].length ?? 3;
                const title = heading[1];
                const Tag = level <= 2 ? "h3" : "h4";
                elements.push(
                    <Tag
                        key={`h-${key++}`}
                        className={
                            level <= 2
                                ? "text-base font-bold text-slate-900 dark:text-gray-100 mt-5 mb-2 first:mt-0"
                                : "text-sm font-semibold text-violet-800 dark:text-violet-300 mt-4 mb-1.5"
                        }
                    >
                        {title}
                    </Tag>
                );
                continue;
            }

            if (/^>\s?/.test(trimmed)) {
                flushList();
                flushParagraph();
                const quote = trimmed.replace(/^>\s?/, "");
                elements.push(
                    <div
                        key={`q-${key++}`}
                        className="my-3 pl-4 border-l-4 border-violet-300 dark:border-violet-600 text-[15px] leading-relaxed text-violet-900/90 dark:text-violet-200"
                    >
                        {renderLessonInline(quote, `q-${key}`)}
                    </div>
                );
                continue;
            }

            const bullet = trimmed.match(/^[-*•]\s+(.+)$/);
            if (bullet) {
                flushParagraph();
                listItems.push(bullet[1]);
                continue;
            }

            flushList();
            paragraph.push(trimmed);
        }

        flushList();
        flushParagraph();
    }

    return <div className="lesson-prose max-w-none">{elements}</div>;
}
