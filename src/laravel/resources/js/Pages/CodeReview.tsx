import axios from 'axios';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ThemedMonacoEditor } from '@/components/ThemedMonacoEditor';
import { LanguageLogoImage } from '@/components/course/LanguageLogoImage';
import { CodeBracketIcon } from '@heroicons/react/24/outline';
import { AppLayout } from '@/components/Sidebar';
import { Breadcrumbs } from '@/components/Breadcrumbs';
import { Head } from '@inertiajs/react';
import { getLanguageVisual } from '@/lib/courseLanguageVisual';
import { waitForKafkaJob } from '@/lib/lessonJobPoll';

const STORAGE_KEY = 'devpath_code_review_state';

interface SonarIssue {
    line: number | null;
    type: string;
    severity: string;
    message: string;
}

interface ExplainedIssue {
    line: number | null;
    type: string;
    severity: string;
    sonar_message: string;
    explanation: string;
    how_to_fix: string;
}

interface AiEvaluation {
    summary: string;
    overall_score: number;
    grade_label: string;
    strengths: string[];
    improvements: string[];
    recommendation: string;
    criteria: Record<string, number>;
    explained_issues: ExplainedIssue[];
}

interface ReviewResult {
    uuid?: string;
    is_code?: boolean;
    detected_language: string;
    editor_language: string;
    metrics?: Record<string, string>;
    issues?: SonarIssue[];
    ai_evaluation?: AiEvaluation;
    overall_score?: number;
    not_code_message?: string;
    not_code_hint?: string;
}

interface PersistedState {
    code: string;
    editorLanguage: string;
    result: ReviewResult | null;
    reviewedCode?: string;
}

function loadFromStorage(): PersistedState | null {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;

        const parsed = JSON.parse(raw) as PersistedState;
        const hasCode = (parsed.code ?? '').trim().length > 0;

        if (!hasCode) {
            sessionStorage.removeItem(STORAGE_KEY);
            return null;
        }

        return parsed;
    } catch {
        sessionStorage.removeItem(STORAGE_KEY);
        return null;
    }
}

function saveToStorage(state: PersistedState) {
    try {
        const hasCode = (state.code ?? '').trim().length > 0;
        if (!hasCode) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // ignore quota errors
    }
}

const CRITERIA_LABELS: Record<string, string> = {
    correctness: 'Корректность',
    readability: 'Читаемость',
    code_structure: 'Структура',
    best_practices: 'Best practices',
    maintainability: 'Поддерживаемость',
};

const ISSUE_TYPE_LABELS: Record<string, string> = {
    VULNERABILITY: 'Уязвимость',
    BUG: 'Ошибка',
    CODE_SMELL: 'Плохая практика',
    SECURITY_HOTSPOT: 'Точка риска',
};

const SEVERITY_LABELS: Record<string, string> = {
    BLOCKER: 'Блокер',
    CRITICAL: 'Критично',
    MAJOR: 'Важно',
    MINOR: 'Незначительно',
    INFO: 'Инфо',
};

const SEVERITY_ACCENT: Record<string, string> = {
    BLOCKER: 'border-l-red-500',
    CRITICAL: 'border-l-red-500',
    MAJOR: 'border-l-orange-500',
    MINOR: 'border-l-amber-400',
    INFO: 'border-l-slate-300',
};

function normalizeLine(line: number | string | null | undefined): number | null {
    const n = Number(line);
    return Number.isFinite(n) && n > 0 ? Math.floor(n) : null;
}

function sortIssuesByLine<T extends { line?: number | string | null }>(issues: T[]): T[] {
    return [...issues].sort((a, b) => {
        const lineA = normalizeLine(a.line) ?? Number.MAX_SAFE_INTEGER;
        const lineB = normalizeLine(b.line) ?? Number.MAX_SAFE_INTEGER;
        return lineA - lineB;
    });
}

function issueTypeLabel(type: string): string {
    return ISSUE_TYPE_LABELS[type] ?? type.replace(/_/g, ' ').toLowerCase();
}

function severityLabel(severity: string): string {
    return SEVERITY_LABELS[severity] ?? severity.toLowerCase();
}

function LanguageChip({ language, compact = false }: { language: string; compact?: boolean }) {
    const visual = getLanguageVisual(language);

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 bg-white dark:border-gray-700 dark:bg-gray-800 ${
                compact ? 'px-2 py-0.5' : 'px-2.5 py-1'
            }`}
        >
            <LanguageLogoImage
                slug={visual.slug}
                className={compact ? 'h-4 w-4 object-contain' : 'h-5 w-5 object-contain'}
            />
            <span className={`font-medium text-slate-700 dark:text-gray-200 ${compact ? 'text-[10px]' : 'text-xs'}`}>
                {visual.label}
            </span>
        </span>
    );
}

function LanguageBadge({ language }: { language: string }) {
    const visual = getLanguageVisual(language);

    return (
        <div className="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-slate-50/60 px-3 py-2.5 dark:border-gray-800 dark:bg-gray-800/40">
            <LanguageLogoImage slug={visual.slug} className="h-9 w-9 object-contain" />
            <div>
                <p className="text-[10px] font-medium uppercase tracking-wider text-slate-400">Язык</p>
                <p className="text-sm font-semibold text-slate-800 dark:text-gray-100">{visual.label}</p>
            </div>
        </div>
    );
}

function ScoreRing({ score, size = 108 }: { score: number; size?: number }) {
    const clamped = Math.min(100, Math.max(0, Number(score) || 0));
    const stroke = 7;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (clamped / 100) * circumference;

    return (
        <div className="relative shrink-0" style={{ width: size, height: size }}>
            <svg width={size} height={size} className="-rotate-90" aria-hidden>
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    className="stroke-slate-100 dark:stroke-gray-800"
                    strokeWidth={stroke}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    className="stroke-violet-500 transition-[stroke-dashoffset] duration-700 ease-out"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-gray-50">
                    {clamped}
                </span>
                <span className="text-[10px] text-slate-400">из 100</span>
            </div>
        </div>
    );
}

function ReviewResultPanel({
    ai,
    result,
    explainedIssues,
    onIssueClick,
}: {
    ai: AiEvaluation;
    result: ReviewResult;
    explainedIssues: ExplainedIssue[];
    onIssueClick: (line: number | null) => void;
}) {
    const score = ai.overall_score ?? result.overall_score ?? 0;

    return (
        <div className="space-y-6">
            <section className="border-b border-slate-200/80 pb-6 dark:border-gray-800">
                <div className="flex items-start gap-5">
                    <ScoreRing score={score} />
                    <div className="min-w-0 flex-1 space-y-3">
                        <div>
                            <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">
                                Итоговая оценка
                            </p>
                            <p className="mt-1 text-sm font-semibold text-violet-700 dark:text-violet-300">
                                {ai.grade_label}
                            </p>
                        </div>
                        {result.detected_language && (
                            <LanguageBadge language={result.detected_language} />
                        )}
                    </div>
                </div>
                <p className="mt-4 text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                    {ai.summary}
                </p>
            </section>

            {ai.criteria && Object.keys(ai.criteria).length > 0 && (
                <section>
                    <h4 className="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">
                        Детальная оценка
                    </h4>
                    <div className="space-y-3">
                        {Object.entries(ai.criteria).map(([key, value]) => (
                            <div key={key}>
                                <div className="mb-1.5 flex justify-between text-xs">
                                    <span className="text-slate-600 dark:text-gray-400">
                                        {CRITERIA_LABELS[key] ?? key}
                                    </span>
                                    <span className="font-medium tabular-nums text-slate-800 dark:text-gray-200">
                                        {value}
                                    </span>
                                </div>
                                <div className="h-1 overflow-hidden rounded-full bg-violet-100 dark:bg-violet-950/50">
                                    <div
                                        className="h-full rounded-full bg-violet-500 transition-all"
                                        style={{ width: `${Math.min(100, Number(value))}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {ai.strengths?.length > 0 && (
                <section>
                    <h4 className="mb-2 text-sm font-semibold text-slate-900 dark:text-gray-100">
                        Сильные стороны
                    </h4>
                    <ul className="space-y-2 border-l-2 border-slate-200 pl-4 dark:border-gray-700">
                        {ai.strengths.map((s, i) => (
                            <li key={i} className="text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                                {s}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {ai.improvements?.length > 0 && (
                <section>
                    <h4 className="mb-2 text-sm font-semibold text-slate-900 dark:text-gray-100">
                        Что улучшить
                    </h4>
                    <ul className="space-y-2 border-l-2 border-slate-300 pl-4 dark:border-gray-600">
                        {ai.improvements.map((s, i) => (
                            <li key={i} className="text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                                {s}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {ai.recommendation && (
                <section className="rounded-xl border border-slate-200/80 px-4 py-3 dark:border-gray-800">
                    <p className="text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        Рекомендация
                    </p>
                    <p className="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-gray-400">
                        {ai.recommendation}
                    </p>
                </section>
            )}

            <section>
                <h4 className="mb-3 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">
                    Замечания ({explainedIssues.length})
                </h4>
                {explainedIssues.length === 0 ? (
                    <p className="text-sm text-slate-500">Критичных замечаний не найдено.</p>
                ) : (
                    <div className="space-y-2">
                        {explainedIssues.map((issue, idx) => (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => onIssueClick(normalizeLine(issue.line))}
                                className={`w-full rounded-lg border border-slate-200/80 border-l-[3px] bg-white p-3 text-left transition hover:border-slate-300 hover:bg-slate-50/80 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:border-gray-700 dark:hover:bg-gray-800/60 ${
                                    SEVERITY_ACCENT[issue.severity] ?? SEVERITY_ACCENT.INFO
                                }`}
                            >
                                <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                    {issue.line && (
                                        <span className="text-xs font-semibold text-slate-800 dark:text-gray-200">
                                            Строка {issue.line}
                                        </span>
                                    )}
                                    <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-gray-800 dark:text-gray-400">
                                        {issueTypeLabel(issue.type)}
                                    </span>
                                    <span className="text-[10px] text-slate-400">{severityLabel(issue.severity)}</span>
                                </div>
                                <p className="text-sm font-medium text-slate-800 dark:text-gray-200">
                                    {issue.explanation}
                                </p>
                                {issue.how_to_fix && (
                                    <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-gray-500">
                                        {issue.how_to_fix}
                                    </p>
                                )}
                            </button>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function getHighlightIssues(result: ReviewResult): SonarIssue[] {
    const byLine = new Map<number, SonarIssue>();

    for (const issue of result.ai_evaluation?.explained_issues ?? []) {
        const line = normalizeLine(issue.line);
        if (line) {
            byLine.set(line, {
                line,
                type: issue.type,
                severity: issue.severity,
                message: issue.explanation || issue.sonar_message,
            });
        }
    }

    for (const issue of result.issues ?? []) {
        const line = normalizeLine(issue.line);
        if (line) {
            byLine.set(line, { ...issue, line });
        }
    }

    return Array.from(byLine.values());
}

export default function CodeReview() {
    const stored = loadFromStorage();

    const [code, setCode] = useState(stored?.code ?? '');
    const [editorLanguage, setEditorLanguage] = useState(stored?.editorLanguage ?? 'php');
    const [result, setResult] = useState<ReviewResult | null>(stored?.result ?? null);
    const [reviewedCode, setReviewedCode] = useState(stored?.reviewedCode ?? '');
    const [checking, setChecking] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const editorRef = useRef<any>(null);
    const monacoRef = useRef<any>(null);
    const decorationsRef = useRef<string[]>([]);

    useEffect(() => {
        localStorage.removeItem(STORAGE_KEY);
    }, []);

    const clearHighlights = useCallback(() => {
        if (decorationsRef.current.length > 0 && editorRef.current) {
            editorRef.current.deltaDecorations(decorationsRef.current, []);
            decorationsRef.current = [];
        }
    }, []);

    const highlightIssues = useCallback((issues: SonarIssue[]) => {
        if (!editorRef.current || !monacoRef.current) {
            return;
        }

        if (decorationsRef.current.length > 0) {
            editorRef.current.deltaDecorations(decorationsRef.current, []);
        }

        const monaco = monacoRef.current;
        const newDecorations = issues
            .map((issue) => {
                const line = normalizeLine(issue.line);
                if (!line) {
                    return null;
                }

                let className = 'code-smell-line';
                switch (issue.type) {
                    case 'VULNERABILITY':
                        className = 'vulnerability-line';
                        break;
                    case 'BUG':
                        className = 'bug-line';
                        break;
                }

                return {
                    range: new monaco.Range(line, 1, line, 1),
                    options: {
                        isWholeLine: true,
                        className,
                        glyphMarginClassName: `${className}-glyph`,
                        glyphMarginHoverMessage: { value: issue.message },
                    },
                };
            })
            .filter(Boolean);

        decorationsRef.current = editorRef.current.deltaDecorations([], newDecorations);
    }, []);

    const persist = useCallback((
        nextCode: string,
        nextLang: string,
        nextResult: ReviewResult | null,
        nextReviewedCode: string,
    ) => {
        saveToStorage({
            code: nextCode,
            editorLanguage: nextLang,
            result: nextResult,
            reviewedCode: nextReviewedCode,
        });
    }, []);

    useEffect(() => {
        persist(code, editorLanguage, result, reviewedCode);
    }, [code, editorLanguage, result, reviewedCode, persist]);

    useEffect(() => {
        if (!code.trim() && result) {
            setResult(null);
            setReviewedCode('');
            clearHighlights();
        }
    }, [code, result, clearHighlights]);

    useEffect(() => {
        if (!result || result.is_code === false || !editorRef.current || !monacoRef.current) {
            return;
        }
        const issues = getHighlightIssues(result);
        if (issues.length > 0) {
            highlightIssues(issues);
        }
    }, [result, highlightIssues]);

    const handleEditorDidMount = (editor: any, monaco: any) => {
        editorRef.current = editor;
        monacoRef.current = monaco;

        const style = document.getElementById('code-review-monaco-styles') ?? document.createElement('style');
        style.id = 'code-review-monaco-styles';
        style.textContent = `
            .vulnerability-line { background-color: rgba(239, 68, 68, 0.1); }
            .vulnerability-line-glyph { background-color: #ef4444 !important; width: 4px !important; margin-left: 3px; border-radius: 2px; }
            .bug-line { background-color: rgba(249, 115, 22, 0.1); }
            .bug-line-glyph { background-color: #f97316 !important; width: 4px !important; margin-left: 3px; border-radius: 2px; }
            .code-smell-line { background-color: rgba(234, 179, 8, 0.1); }
            .code-smell-line-glyph { background-color: #eab308 !important; width: 4px !important; margin-left: 3px; border-radius: 2px; }
        `;
        if (!style.parentElement) {
            document.head.appendChild(style);
        }

        if (result) {
            const issues = getHighlightIssues(result);
            if (issues.length > 0) {
                highlightIssues(issues);
            }
        }
    };

    const handleCheck = async () => {
        if (!code.trim()) {
            setError('Вставьте или напишите код для проверки');
            return;
        }

        setChecking(true);
        setError(null);

        try {
            const { data: initialData } = await axios.post('/code-review/analyze', { code });
            let data = initialData as Record<string, unknown> & { status?: string; job_id?: string; success?: boolean; error?: string };

            if (data.status === 'generating_analyze' && typeof data.job_id === 'string') {
                data = await waitForKafkaJob(data.job_id, 90_000);
            }

            if (data.error || data.success === false) {
                throw new Error(typeof data.error === 'string' ? data.error : 'Ошибка проверки');
            }

            const review: ReviewResult = {
                uuid: data.uuid as string,
                is_code: data.is_code !== false,
                detected_language: data.detected_language as string,
                editor_language: data.editor_language as string,
                metrics: data.metrics as Record<string, string> | undefined,
                issues: data.issues as SonarIssue[] | undefined,
                ai_evaluation: data.ai_evaluation as AiEvaluation | undefined,
                overall_score: data.overall_score as number | undefined,
                not_code_message: data.not_code_message as string | undefined,
                not_code_hint: data.not_code_hint as string | undefined,
            };

            setResult(review);
            setReviewedCode(code);
            setEditorLanguage((data.editor_language as string) || editorLanguage);

            const issues = getHighlightIssues(review);
            if (issues.length > 0 && editorRef.current && monacoRef.current) {
                highlightIssues(issues);
                window.setTimeout(() => highlightIssues(issues), 100);
            }
        } catch (e: any) {
            setError(e?.response?.data?.error || e?.message || 'Не удалось проверить код');
        } finally {
            setChecking(false);
        }
    };

    const handleClear = () => {
        setCode('');
        setResult(null);
        setReviewedCode('');
        setError(null);
        setEditorLanguage('php');
        sessionStorage.removeItem(STORAGE_KEY);

        clearHighlights();
    };

    const isNotCode = result?.is_code === false;
    const ai = result?.ai_evaluation;
    const explainedIssues = sortIssuesByLine(ai?.explained_issues ?? []);
    const isResultStale = Boolean(result && reviewedCode && code !== reviewedCode && !isNotCode);

    const scrollToLine = (line: number | null) => {
        if (!line || !editorRef.current) return;
        editorRef.current.revealLineInCenter(line);
        editorRef.current.setPosition({ lineNumber: line, column: 1 });
        editorRef.current.focus();
    };

    return (
        <AppLayout>
            <Head title="Анализ кода" />

            <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="shrink-0 px-4 md:px-6">
                    <Breadcrumbs
                        crumbs={[
                            { label: "DevPath", path: "/main" },
                            { label: "Анализ кода", path: "/code-review" },
                        ]}
                    />
                </div>

                <div className="flex min-h-0 flex-1 flex-col px-4 pb-4 pt-0 md:px-6 md:pb-6">
                    <div className="mb-4 shrink-0">
                        <h2 className="text-2xl font-bold text-slate-900 dark:text-gray-100">Анализ кода</h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-gray-400">
                            Вставьте код и нажмите «Проверить»
                        </p>
                    </div>

                    <div className="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2 lg:grid-rows-1">
                        <div className="surface-elevated flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                            <div className="flex shrink-0 items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-gray-800">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-semibold text-slate-800 dark:text-gray-200">Редактор</span>
                                    {result?.is_code !== false && result?.detected_language && (
                                        <LanguageChip language={result.detected_language} compact />
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={handleCheck}
                                        disabled={checking}
                                        className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {checking ? 'Проверка…' : 'Проверить'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleClear}
                                        disabled={checking}
                                        className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Очистить всё
                                    </button>
                                </div>
                            </div>

                            {isResultStale && (
                                <div className="shrink-0 border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                                    Код изменён после проверки — подсветка может быть неактуальна. Нажмите «Проверить» снова.
                                </div>
                            )}

                            <div className={`code-review-monaco relative h-full min-h-0 flex-1 bg-white dark:bg-black ${checking ? 'pointer-events-none opacity-70' : ''}`}>
                                {checking && (
                                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/60 backdrop-blur-[1px] dark:bg-gray-950/60">
                                        <div className="flex flex-col items-center gap-2">
                                            <span className="h-8 w-8 animate-spin rounded-full border-2 border-violet-200 border-t-violet-600" />
                                            <span className="text-sm font-medium text-violet-700">Анализ кода…</span>
                                        </div>
                                    </div>
                                )}
                                <ThemedMonacoEditor
                                    height="100%"
                                    language={editorLanguage}
                                    value={code}
                                    onChange={(value) => setCode(value || '')}
                                    onMount={handleEditorDidMount}
                                    options={{
                                        minimap: { enabled: false },
                                        fontSize: 14,
                                        lineNumbers: 'on',
                                        glyphMargin: true,
                                        scrollBeyondLastLine: false,
                                        automaticLayout: true,
                                    }}
                                />
                            </div>

                            {error && (
                                <div className="shrink-0 border-t border-red-100 bg-red-50 px-4 py-2 text-sm text-red-600 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                                    {error}
                                </div>
                            )}
                        </div>

                        <div className="surface-elevated flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                            <div className="shrink-0 border-b border-slate-100 px-4 py-3 dark:border-gray-800">
                                <span className="text-sm font-semibold text-slate-800 dark:text-gray-200">Результат проверки</span>
                            </div>

                            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                                {!result && !checking && (
                                    <div className="flex h-full min-h-[12rem] flex-col items-center justify-center text-center text-slate-400 dark:text-gray-500">
                                        <p className="text-sm">Результаты анализа кода появятся здесь</p>
                                    </div>
                                )}

                                {result && isNotCode && (
                                    <div className="flex h-full min-h-[12rem] flex-col items-center justify-center px-8 py-12 text-center">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            strokeWidth={1.5}
                                            stroke="currentColor"
                                            className="mb-8 size-16 text-violet-300"
                                            aria-hidden
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"
                                            />
                                        </svg>
                                        <h3 className="text-2xl font-bold text-slate-900 dark:text-gray-100">
                                            Это не похоже на код
                                        </h3>
                                        <p className="mt-4 max-w-md text-base leading-relaxed text-slate-600">
                                            {result.not_code_message
                                                ?? 'Вставьте фрагмент на одном из поддерживаемых языков программирования — тогда мы сможем его проверить.'}
                                        </p>
                                        {result.not_code_hint && (
                                            <p className="mt-6 flex max-w-md items-start justify-center gap-3 text-left text-base text-violet-700">
                                                <CodeBracketIcon
                                                    className="mt-0.5 size-6 shrink-0 text-violet-300"
                                                    strokeWidth={1.5}
                                                    aria-hidden
                                                />
                                                <span>{result.not_code_hint}</span>
                                            </p>
                                        )}
                                    </div>
                                )}

                                {result && !isNotCode && ai && (
                                    <ReviewResultPanel
                                        ai={ai}
                                        result={result}
                                        explainedIssues={explainedIssues}
                                        onIssueClick={scrollToLine}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
