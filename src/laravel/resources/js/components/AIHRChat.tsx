import { useState, useRef, useEffect, useCallback } from "react";
import axios from "axios";
import { ThemedMonacoEditor } from "@/components/ThemedMonacoEditor";
import {
    PaperAirplaneIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    CodeBracketIcon,
    ChatBubbleLeftRightIcon,
    ClockIcon,
    ArrowsPointingOutIcon,
    ArrowsPointingInIcon,
} from "@heroicons/react/24/outline";
import { waitForKafkaJob } from "@/lib/lessonJobPoll";
import Modal from "@/components/Modal";

const LANGUAGES = ["PHP", "Python", "JavaScript", "TypeScript", "Java", "C++", "C#", "Go", "Ruby"];
const LEVELS = ["Junior", "Middle", "Senior"] as const;

const LANG_EXT: Record<string, string> = {
    PHP: "php", Python: "py", JavaScript: "js", TypeScript: "ts", Java: "java",
    "C++": "cpp", "C#": "cs", Go: "go", Ruby: "rb",
};

const LANG_MONACO: Record<string, string> = {
    PHP: "php", Python: "python", JavaScript: "javascript", TypeScript: "typescript",
    Java: "java", "C++": "cpp", "C#": "csharp", Go: "go", Ruby: "ruby",
};

const SEL_BTN =
    "border-orange-500 bg-orange-50 text-orange-600 font-semibold dark:border-orange-500 dark:bg-orange-950/30 dark:text-orange-400";
const IDLE_BTN =
    "border-gray-200 bg-white text-gray-500 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400";

type Phase = "setup" | "chat" | "verdict" | "stopped_early";

interface ChatMessage {
    id: string;
    role: "user" | "assistant" | "system";
    text: string;
    codeBlock?: string | null;
    lang?: string | null;
}

interface Verdict {
    decision?: string;
    summary?: string;
    strengths?: string[];
    weaknesses?: string[];
    psycho_note?: string;
    star_scores?: Record<string, number>;
    technical_level?: string;
    code_quality?: string;
    conduct_termination?: boolean;
    terminated_for_conduct?: boolean;
}

function useTimer(running: boolean) {
    const [secs, setSecs] = useState(0);
    useEffect(() => {
        if (!running) return;
        const id = setInterval(() => setSecs((s) => s + 1), 1000);
        return () => clearInterval(id);
    }, [running]);
    const reset = () => setSecs(0);
    const fmt = (s: number) =>
        `${String(Math.floor(s / 60)).padStart(2, "0")}:${String(s % 60).padStart(2, "0")}`;
    return { secs, fmt: fmt(secs), reset };
}

function MentorCodeBlock({ code, lang }: { code: string; lang?: string | null }) {
    const [expanded, setExpanded] = useState(false);
    const lineCount = code.split("\n").length;
    const needsExpand = lineCount > 14 || code.length > 480;

    return (
        <div className="rounded-xl overflow-hidden border-2 border-orange-400 dark:border-orange-500 mb-2 bg-white dark:bg-gray-900">
            <div className="flex items-center justify-between gap-2 px-3.5 py-2 border-b border-orange-200 dark:border-orange-800 bg-orange-50/60 dark:bg-orange-950/30">
                <span className="text-xs text-slate-600 dark:text-gray-300 font-mono">
                    solution.{LANG_EXT[lang ?? ""] || "txt"}
                </span>
                {needsExpand && (
                    <button
                        type="button"
                        onClick={() => setExpanded((v) => !v)}
                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-violet-700 dark:text-violet-400 hover:text-orange-600 dark:hover:text-orange-400 transition-colors shrink-0"
                    >
                        {expanded ? (
                            <>
                                <ArrowsPointingInIcon className="w-3.5 h-3.5" />
                                Свернуть
                            </>
                        ) : (
                            <>
                                <ArrowsPointingOutIcon className="w-3.5 h-3.5" />
                                Развернуть
                            </>
                        )}
                    </button>
                )}
            </div>
            <pre
                className={`m-0 p-3.5 text-sm font-mono text-slate-900 dark:text-slate-200 overflow-x-auto leading-relaxed bg-white dark:bg-gray-950 ${
                    expanded ? "max-h-[min(70vh,640px)] overflow-y-auto" : "max-h-[220px] overflow-y-auto"
                }`}
            >
                {code}
            </pre>
        </div>
    );
}

function Bubble({ msg, interviewerName = "Алексей" }: { msg: ChatMessage; interviewerName?: string }) {
    const isUser = msg.role === "user";
    const isSystem = msg.role === "system";
    const interviewerInitials = interviewerName.slice(0, 2);

    if (isSystem) {
        return (
            <div className="text-center my-4">
                <span className="text-xs text-gray-400 dark:text-gray-500 tracking-wide">
                    {msg.text}
                </span>
            </div>
        );
    }

    return (
        <div className={`flex mb-4 gap-3 items-end ${isUser ? "justify-end" : "justify-start"}`}>
            {!isUser && (
                <div
                    className="w-10 h-10 rounded-full shrink-0 bg-violet-600 flex items-center justify-center text-xs font-bold text-white"
                    title={interviewerName}
                >
                    {interviewerInitials}
                </div>
            )}
            <div className="max-w-[min(100%,520px)]">
                {msg.codeBlock && <MentorCodeBlock code={msg.codeBlock} lang={msg.lang} />}
                {msg.text && (
                    <div
                        className={`px-4 py-3 text-[15px] leading-relaxed whitespace-pre-wrap break-words ${
                            isUser
                                ? "rounded-2xl rounded-br-sm bg-violet-600 text-white"
                                : "rounded-2xl rounded-bl-sm bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700"
                        }`}
                    >
                        {msg.text}
                    </div>
                )}
            </div>
            {isUser && (
                <div className="w-10 h-10 rounded-full shrink-0 bg-violet-600 flex items-center justify-center text-xs font-bold text-white">
                    Вы
                </div>
            )}
        </div>
    );
}

function EditorResizeHandle(props: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            role="separator"
            aria-label="Изменить высоту редактора"
            className="shrink-0 h-2 cursor-ns-resize flex items-center justify-center group touch-none bg-orange-50/50 dark:bg-orange-950/20 border-y border-orange-200 dark:border-orange-800"
            {...props}
        >
            <div className="w-12 h-1 rounded-full bg-violet-200 group-hover:bg-violet-500 dark:group-hover:bg-violet-400 transition-colors" />
        </div>
    );
}

function CodeTaskBlock({
    lang,
    initialCode,
    onSubmit,
}: {
    lang: string;
    initialCode: string;
    onSubmit: (code: string) => void;
}) {
    const [code, setCode] = useState(initialCode);
    const [submitted, setSubmitted] = useState(false);
    const [editorHeight, setEditorHeight] = useState(280);
    const dragRef = useRef<{ startY: number; startH: number } | null>(null);
    const monacoLang = LANG_MONACO[lang] || "javascript";

    const editorResize = {
        onPointerDown: (e: React.PointerEvent) => {
            if (submitted) return;
            e.preventDefault();
            dragRef.current = { startY: e.clientY, startH: editorHeight };
            (e.target as HTMLElement).setPointerCapture(e.pointerId);
        },
        onPointerMove: (e: React.PointerEvent) => {
            if (!dragRef.current || submitted) return;
            const delta = e.clientY - dragRef.current.startY;
            setEditorHeight(Math.min(640, Math.max(200, dragRef.current.startH + delta)));
        },
        onPointerUp: () => {
            dragRef.current = null;
        },
        onPointerCancel: () => {
            dragRef.current = null;
        },
    };

    const handleSubmit = () => {
        if (submitted || !code.trim()) return;
        setSubmitted(true);
        onSubmit(code);
    };

    return (
        <div className="border-2 border-orange-400 dark:border-orange-500 rounded-xl overflow-hidden mb-4 ml-[52px] bg-white dark:bg-gray-950">
            <div className="flex items-center justify-between px-3.5 py-2 border-b border-orange-200 dark:border-orange-800 bg-orange-50/60 dark:bg-orange-950/30">
                <div className="flex items-center gap-2 text-slate-600 dark:text-gray-300">
                    <CodeBracketIcon className="w-4 h-4" />
                    <span className="text-xs font-mono">solution.{LANG_EXT[lang] || "txt"}</span>
                </div>
            </div>
            <div className="bg-white dark:bg-black" style={{ height: editorHeight }}>
                {submitted ? (
                    <div className="h-full flex items-center justify-center text-gray-500 text-sm bg-white dark:bg-black">
                        Код отправлен
                    </div>
                ) : (
                    <ThemedMonacoEditor
                        height={`${editorHeight}px`}
                        language={monacoLang}
                        value={code}
                        onChange={(v) => setCode(v ?? "")}
                        options={{
                            fontSize: 14,
                            minimap: { enabled: false },
                            scrollBeyondLastLine: false,
                            automaticLayout: true,
                        }}
                    />
                )}
            </div>
            {!submitted && <EditorResizeHandle {...editorResize} />}
            {!submitted && (
                <div className="px-3.5 py-2.5 border-t border-orange-200 dark:border-orange-800 flex justify-end bg-white dark:bg-gray-900">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        className="px-5 py-2 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 transition-colors"
                    >
                        Отправить решение
                    </button>
                </div>
            )}
        </div>
    );
}

function StarScores({ scores }: { scores: Record<string, number> }) {
    const labels: Record<string, string> = {
        situation: "Ситуация",
        task: "Задача",
        action: "Действие",
        result: "Результат",
    };

    return (
        <div className="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
            {Object.entries(scores).map(([key, val]) => (
                <div key={key} className="text-xs text-gray-600 dark:text-gray-400 flex justify-between gap-2">
                    <span>{labels[key] ?? key}</span>
                    <span className="font-semibold text-violet-700 dark:text-violet-400">{val}/5</span>
                </div>
            ))}
        </div>
    );
}

function StoppedEarlyCard() {
    return (
        <div className="ml-[52px] my-3 p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div className="font-bold text-base text-violet-700 dark:text-violet-400 mb-2">
                Собеседование завершено досрочно
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                Ваши ответы за пройденные этапы успешно сохранены и переданы в рекрутинговый отдел.
                Рекрутер свяжется с вами для обсуждения дальнейших шагов.
            </p>
        </div>
    );
}

function VerdictCard({ verdict }: { verdict: Verdict }) {
    const hired = verdict?.decision === "hire";
    const conductReject = Boolean(verdict?.conduct_termination ?? verdict?.terminated_for_conduct);

    return (
        <div className="ml-[52px] my-3 p-5 rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-white dark:bg-gray-900 shadow-sm">
            <div className="mb-3">
                <div className={`font-bold text-lg ${hired ? "text-violet-700 dark:text-violet-400" : "text-gray-700 dark:text-gray-300"}`}>
                    {hired ? "ПРИНЯТ" : conductReject ? "ОТКАЗ · НАРУШЕНИЕ ПРАВИЛ" : "ОТКАЗ"}
                </div>
                <div className="text-xs text-gray-400">Вердикт HR · DevPath</div>
            </div>

            {verdict?.summary && (
                <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed border-b border-gray-100 dark:border-gray-800 pb-3 mb-3">
                    {verdict.summary}
                </p>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {verdict?.strengths && verdict.strengths.length > 0 && (
                    <div>
                        <div className="text-[10px] font-semibold text-orange-500 uppercase tracking-wider mb-1.5">
                            Сильные стороны
                        </div>
                        {verdict.strengths.map((s, i) => (
                            <div key={i} className="text-xs text-gray-600 dark:text-gray-400 pl-2.5 mb-1 border-l-2 border-orange-400">
                                {s}
                            </div>
                        ))}
                    </div>
                )}
                {verdict?.weaknesses && verdict.weaknesses.length > 0 && (
                    <div>
                        <div className="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                            Зоны роста
                        </div>
                        {verdict.weaknesses.map((s, i) => (
                            <div key={i} className="text-xs text-gray-600 dark:text-gray-400 pl-2.5 mb-1 border-l-2 border-gray-300 dark:border-gray-600">
                                {s}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {verdict?.star_scores && Object.keys(verdict.star_scores).length > 0 && (
                <StarScores scores={verdict.star_scores} />
            )}

            {verdict?.code_quality && verdict.code_quality !== "n/a" && (
                <p className="text-xs text-gray-600 dark:text-gray-400 mt-3">
                    <span className="font-semibold text-violet-700 dark:text-violet-400">Код: </span>
                    {verdict.code_quality}
                </p>
            )}

            {verdict?.psycho_note && verdict.psycho_note !== "n/a" && (
                <div className="mt-3 pt-3 border-t border-violet-100 dark:border-violet-900/50">
                    <div className="text-[10px] font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-wider mb-1">
                        {conductReject ? "Оценка навыков и коммуникации" : "Психологический профиль"}
                    </div>
                    <p className="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{verdict.psycho_note}</p>
                </div>
            )}
        </div>
    );
}

export default function AIHRChatPanel() {
    const [lang, setLang] = useState<string | null>(null);
    const [level, setLevel] = useState<string | null>(null);
    const [phase, setPhase] = useState<Phase>("setup");
    const [interviewId, setInterviewId] = useState<number | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [inlineEditors, setInlineEditors] = useState<Record<string, boolean>>({});
    const [codeStarters, setCodeStarters] = useState<Record<string, string>>({});
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(false);
    const [stopping, setStopping] = useState(false);
    const [showStopConfirm, setShowStopConfirm] = useState(false);
    const [stopError, setStopError] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [verdict, setVerdict] = useState<Verdict | null>(null);
    const [interviewerName, setInterviewerName] = useState("Алексей");
    const scrollRef = useRef<HTMLDivElement>(null);
    const { fmt: timerFmt, reset: resetTimer } = useTimer(phase === "chat");

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
    }, [messages, loading, verdict]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get("from_senior") !== "1") return;

        const dir = params.get("direction");
        if (dir) {
            const normalized = dir.toUpperCase();
            if (LANGUAGES.includes(normalized)) {
                setLang(normalized);
            }
        }
        const lvl = params.get("level");
        if (lvl && (LEVELS as readonly string[]).includes(lvl)) {
            setLevel(lvl);
        }

        void import("@/lib/seniorProgramStorage").then(({ markSeniorModeTried }) => {
            markSeniorModeTried(dir ?? "PHP", "interview");
        });
    }, []);

    const appendAssistant = useCallback((text: string, hasCodeTask: boolean, codeStarter?: string | null) => {
        const msgId = Date.now().toString();
        setMessages((prev) => [...prev, { id: msgId, role: "assistant", text }]);
        if (hasCodeTask) {
            setInlineEditors((e) => ({ ...e, [msgId]: true }));
            if (codeStarter) {
                setCodeStarters((s) => ({ ...s, [msgId]: codeStarter }));
            }
        }
    }, []);

    const handleApiError = (err: unknown) => {
        let msg = "Ошибка соединения";
        if (axios.isAxiosError(err)) {
            if (err.response?.status === 504) {
                msg = "Сервер не успел ответить. Проверьте, что Ollama доступна и запущен воркер kafka:hr-interview-consume.";
            } else {
                msg = (err.response?.data as { error?: string })?.error ?? err.message;
            }
        }
        setError(msg);
        setMessages((prev) => [
            ...prev,
            { id: Date.now().toString(), role: "assistant", text: `Ошибка: ${msg}` },
        ]);
    };

    const applyResponse = (data: {
        message?: string;
        has_code_task?: boolean;
        code_starter?: string | null;
        verdict?: Verdict | null;
        stopped_early?: boolean;
    }) => {
        if (data.stopped_early) {
            setMessages((prev) => [
                ...prev,
                { id: Date.now().toString(), role: "system", text: "Собеседование завершено досрочно" },
            ]);
            setPhase("stopped_early");
            return;
        }
        if (data.verdict) {
            setVerdict(data.verdict);
            setMessages((prev) => [
                ...prev,
                { id: Date.now().toString(), role: "system", text: "Собеседование завершено" },
            ]);
            setPhase("verdict");
            return;
        }
        if (data.message) {
            appendAssistant(data.message, Boolean(data.has_code_task), data.code_starter);
        }
    };

    const startInterview = async () => {
        if (!lang || !level) return;
        setError(null);
        setPhase("chat");
        resetTimer();
        setLoading(true);
        setMessages([]);
        setInlineEditors({});
        setCodeStarters({});
        setVerdict(null);

        try {
            const { data } = await axios.post("/ai-hr/start", { direction: lang, level });
            setInterviewId(data.interview_id);
            if (data.interviewer) {
                setInterviewerName(data.interviewer);
            }
            appendAssistant(data.message, Boolean(data.has_code_task));
        } catch (err) {
            handleApiError(err);
        } finally {
            setLoading(false);
        }
    };

    const sendMessage = async (textOverride?: string, codeOverride?: string | null) => {
        const userText = textOverride ?? input.trim();
        const userCode = codeOverride ?? null;
        if (!interviewId || (!userText && !userCode)) return;

        const userMsg: ChatMessage = {
            id: Date.now().toString(),
            role: "user",
            text: userCode ? "" : userText,
            codeBlock: userCode,
            lang,
        };
        setMessages((prev) => [...prev, userMsg]);
        setInput("");
        setLoading(true);
        setError(null);

        try {
            const { data: initialData } = userCode
                ? await axios.post("/ai-hr/code", { interview_id: interviewId, code: userCode })
                : await axios.post("/ai-hr/reply", { interview_id: interviewId, message: userText });

            let data = initialData as {
                status?: string;
                job_id?: string;
                message?: string;
                has_code_task?: boolean;
                code_starter?: string | null;
                verdict?: Verdict | null;
                stopped_early?: boolean;
            };

            if (data.status === "generating_chat" && typeof data.job_id === "string") {
                data = await waitForKafkaJob(data.job_id, 60_000);
            }

            applyResponse(data);
        } catch (err) {
            handleApiError(err);
        } finally {
            setLoading(false);
        }
    };

    const stopInterview = async () => {
        if (!interviewId || stopping) return;

        setStopping(true);
        setStopError(null);

        try {
            const { data } = await axios.post("/ai-hr/stop", { interview_id: interviewId });
            setShowStopConfirm(false);
            setLoading(false);
            applyResponse(data);
        } catch (err) {
            let msg = "Не удалось завершить собеседование";
            if (axios.isAxiosError(err)) {
                msg = (err.response?.data as { error?: string })?.error ?? err.message;
            }
            setStopError(msg);
        } finally {
            setStopping(false);
        }
    };

    const resetAll = () => {
        setLang(null);
        setLevel(null);
        setPhase("setup");
        setInterviewId(null);
        setMessages([]);
        setInput("");
        setLoading(false);
        setVerdict(null);
        setInlineEditors({});
        setCodeStarters({});
        setError(null);
        resetTimer();
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    return (
        <div className="flex flex-col h-full bg-white dark:bg-gray-950 overflow-hidden font-sans">
            <div className="flex-1 flex flex-col min-h-0 w-full bg-white px-[100px] pt-4 pb-3 dark:bg-gray-950">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4 shrink-0">
                    Техническое собеседование с AI HR
                </h1>

                <div className="flex flex-1 min-h-0 rounded-2xl surface-elevated">
                <div className="flex flex-1 min-h-0 min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    {}
                    <div className="flex-1 flex flex-col min-w-0 min-h-0">
                        <header className="flex items-center gap-3 px-5 h-14 border-b border-gray-200 dark:border-gray-800 shrink-0">
                            <div className="w-9 h-9 rounded-full bg-violet-600 flex items-center justify-center shrink-0 text-xs font-bold text-white">
                                {interviewerName.slice(0, 2)}
                            </div>
                            <span className="font-semibold text-sm text-gray-900 dark:text-gray-100">
                                AI HR · {interviewerName}
                            </span>
                        </header>

                        <div ref={scrollRef} className="flex-1 overflow-y-auto px-5 py-5 min-h-0 bg-white dark:bg-gray-950">
                            {phase === "setup" && (
                                <div className="h-full flex flex-col items-center justify-center text-gray-400 text-center">
                                    <ChatBubbleLeftRightIcon className="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm leading-relaxed">
                                        Выберите язык и уровень
                                        <br />
                                        на панели справа
                                    </p>
                                </div>
                            )}

                            {messages.map((msg) => (
                                <div key={msg.id}>
                                    <Bubble msg={msg} interviewerName={interviewerName} />
                                    {inlineEditors[msg.id] && lang && (
                                        <CodeTaskBlock
                                            lang={lang}
                                            initialCode={codeStarters[msg.id] ?? `// Исправьте код согласно условию задачи\n`}
                                            onSubmit={(code) => {
                                                setInlineEditors((e) => ({ ...e, [msg.id]: false }));
                                                sendMessage("", code);
                                            }}
                                        />
                                    )}
                                </div>
                            ))}

                            {phase === "verdict" && verdict && <VerdictCard verdict={verdict} />}
                            {phase === "stopped_early" && <StoppedEarlyCard />}

                            {loading && (
                                <div className="flex gap-3 items-center mb-4">
                                    <div className="w-10 h-10 rounded-full bg-violet-600 flex items-center justify-center text-xs font-bold text-white">
                                        {interviewerName.slice(0, 2)}
                                    </div>
                                    <div className="px-4 py-3 rounded-2xl rounded-bl-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex gap-1">
                                        {[0, 1, 2].map((i) => (
                                            <span
                                                key={i}
                                                className="w-2 h-2 rounded-full bg-violet-600 opacity-40 animate-bounce"
                                                style={{ animationDelay: `${i * 0.15}s` }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {error && phase === "chat" && (
                            <div className="shrink-0 px-5 py-2 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                                {error}
                            </div>
                        )}

                        {phase === "chat" && (
                            <footer className="shrink-0 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 pt-3 pb-4">
                                <div className="flex gap-2 items-center p-1.5 rounded-xl border-2 border-violet-400 dark:border-violet-600 bg-white dark:bg-gray-900">
                                    <input
                                        type="text"
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder="Введите ответ…"
                                        disabled={loading || stopping}
                                        className="flex-1 min-w-0 px-2 py-2 bg-transparent text-[15px] outline-none text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 disabled:opacity-50"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => sendMessage()}
                                        disabled={!input.trim() || loading || stopping}
                                        className="shrink-0 px-3 py-2 bg-violet-600 enabled:hover:bg-orange-500 disabled:bg-slate-200 disabled:text-slate-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500 text-white text-sm font-semibold rounded-lg transition-colors"
                                    >
                                        Отправить
                                    </button>
                                </div>
                            </footer>
                        )}
                    </div>

                    {}
                    <aside className="w-[260px] shrink-0 border-l border-gray-200 dark:border-gray-800 flex flex-col overflow-y-auto">
                        <div className="p-5">
                            <div className="mb-5">
                                <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-0.5">
                                    DevPath
                                </div>
                                <div className="text-base font-bold leading-tight text-gray-900 dark:text-gray-100">
                                    AI HR
                                    <br />
                                    Собеседование
                                </div>
                            </div>

                            <div className="mb-4">
                                <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                    Язык программирования
                                </div>
                                <div className="grid grid-cols-3 gap-1.5">
                                    {LANGUAGES.map((l) => {
                                        const sel = lang === l;
                                        return (
                                            <button
                                                key={l}
                                                type="button"
                                                disabled={phase !== "setup"}
                                                onClick={() => setLang(l)}
                                                className={`py-2 rounded-lg text-xs border transition ${
                                                    sel ? SEL_BTN : IDLE_BTN
                                                } ${phase !== "setup" ? "opacity-60 cursor-default" : "cursor-pointer"}`}
                                            >
                                                {l}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="mb-5">
                                <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                    Уровень
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    {LEVELS.map((lv) => {
                                        const sel = level === lv;
                                        return (
                                            <button
                                                key={lv}
                                                type="button"
                                                disabled={phase !== "setup"}
                                                onClick={() => setLevel(lv)}
                                                className={`py-2.5 px-3.5 rounded-lg text-left text-sm border transition ${
                                                    sel ? SEL_BTN : IDLE_BTN
                                                } ${phase !== "setup" ? "opacity-60 cursor-default" : "cursor-pointer"}`}
                                            >
                                                {sel ? "▸ " : ""}
                                                {lv}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {phase === "setup" ? (
                                <button
                                    type="button"
                                    onClick={startInterview}
                                    disabled={!lang || !level || loading}
                                    className="w-full py-3 rounded-xl text-sm font-semibold text-white disabled:bg-gray-100 disabled:text-gray-300 dark:disabled:bg-gray-800 bg-violet-600 enabled:hover:bg-orange-500 transition-colors"
                                >
                                    Начать собеседование
                                </button>
                            ) : (
                                <div className="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                                    <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">
                                        {phase === "verdict" || phase === "stopped_early" ? (
                                            <span className="flex items-center gap-1">
                                                <CheckCircleIcon className="w-3.5 h-3.5" /> Завершено
                                            </span>
                                        ) : (
                                            "Идёт собеседование"
                                        )}
                                    </div>
                                    <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {level} · {lang}
                                    </div>
                                    {phase === "chat" && (
                                        <>
                                            <div className="flex items-center gap-1.5 mt-2">
                                                <ClockIcon className="w-4 h-4 text-orange-500" />
                                                <span className="text-xl font-bold text-violet-700 dark:text-violet-400 tabular-nums">
                                                    {timerFmt}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setStopError(null);
                                                    setShowStopConfirm(true);
                                                }}
                                                disabled={stopping}
                                                className="mt-3 w-full py-2.5 text-xs font-semibold text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-violet-400 hover:text-violet-700 dark:hover:text-violet-400 disabled:opacity-50 transition-colors"
                                            >
                                                Остановить собеседование
                                            </button>
                                        </>
                                    )}
                                    {(phase === "verdict" || phase === "stopped_early") && (
                                        <button
                                            type="button"
                                            onClick={resetAll}
                                            className="mt-3 w-full py-2.5 text-xs font-semibold border border-gray-200 dark:border-gray-700 rounded-lg text-gray-600 dark:text-gray-400 hover:border-violet-400 hover:text-violet-700 dark:hover:text-violet-400 transition-colors flex items-center justify-center gap-1.5"
                                        >
                                            <ArrowPathIcon className="w-3.5 h-3.5" />
                                            Новое собеседование
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </aside>
                </div>
                </div>
            </div>

            <Modal
                show={showStopConfirm}
                onClose={() => {
                    if (stopping) return;
                    setShowStopConfirm(false);
                    setStopError(null);
                }}
                maxWidth="sm"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Завершить собеседование?
                    </h2>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        Собеседование будет остановлено досрочно. Пройденные ответы сохранятся.
                    </p>
                    {stopError && (
                        <p className="mt-3 text-sm text-red-600 dark:text-red-400">
                            {stopError}
                        </p>
                    )}
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => {
                                setShowStopConfirm(false);
                                setStopError(null);
                            }}
                            disabled={stopping}
                            className="px-4 py-2 text-sm font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-violet-400 hover:text-violet-700 dark:hover:text-violet-400 transition-colors disabled:opacity-50"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            onClick={() => void stopInterview()}
                            disabled={stopping}
                            className="px-4 py-2 text-sm font-semibold rounded-lg text-white bg-violet-600 hover:bg-orange-500 transition-colors disabled:opacity-50"
                        >
                            {stopping ? "Завершение…" : "Завершить"}
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
