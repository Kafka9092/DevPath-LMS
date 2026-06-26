import { Input as TextField } from "@/components/ui/input";
import { Button as ActionBtn } from "@/components/ui/button";
import { useState } from "react";

interface ChatFooterProps {
    mode: 'tutoring' | 'interview' | 'codeInspection';
}

export default function MessageSender({ mode }: ChatFooterProps) {
    const [draft, setDraft] = useState("");

    const getInputHint = () => {
        const hints = {
            tutoring: "Задай вопрос по теме...",
            interview: "Введи свой ответ...",
            codeInspection: "Вставь код сюда..."
        };
        return hints[mode];
    };

    const sendMessage = () => {
        if (!draft.trim()) return;

        const logPrefix = {
            tutoring: "Обучение",
            interview: "AI-HR",
            codeInspection: "Анализ кода"
        };

        console.log(`${logPrefix[mode]}: ${draft}`);
        setDraft("");
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        sendMessage();
    };

    return (
        <div className="mt-4 z-10">
            <form onSubmit={handleFormSubmit} className="flex gap-2 bg-white p-2 rounded-xl shadow-lg">
                <TextField
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder={getInputHint()}
                    className="border-0 focus-visible:ring-0 focus-visible:ring-offset-0"
                />
                <ActionBtn type="submit" className="bg-black hover:bg-gray-800 text-white px-6 rounded-xl">
                    Отправить
                </ActionBtn>
            </form>
        </div>
    );
}
