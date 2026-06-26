import React from "react";

export function DeleteCourseModal({
    open,
    title,
    onConfirm,
    onCancel,
    loading,
}: {
    open: boolean;
    title: string;
    onConfirm: () => void;
    onCancel: () => void;
    loading?: boolean;
}) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button type="button" className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={onCancel} aria-label="Закрыть" />
            <div className="relative w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 shadow-2xl p-6">
                <h3 className="text-lg font-semibold text-slate-900 dark:text-gray-50">Удалить курс?</h3>
                <p className="text-sm text-slate-500 dark:text-gray-400 mt-2">
                    «{title}» исчезнет из списка. Прогресс по этому курсу будет сброшен. Это действие нельзя отменить.
                </p>
                <div className="flex gap-3 mt-6 justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={loading}
                        className="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:hover:bg-gray-800 rounded-xl transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={loading}
                        className="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors disabled:opacity-60"
                    >
                        {loading ? "Удаление…" : "Удалить"}
                    </button>
                </div>
            </div>
        </div>
    );
}
