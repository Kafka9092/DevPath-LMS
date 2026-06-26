export function requestLessonNotificationPermission(): void {
    if (typeof window === "undefined" || !("Notification" in window)) return;
    if (Notification.permission === "default") {
        void Notification.requestPermission();
    }
}

export function notifyLessonReady(title: string, body: string): void {
    if (typeof window === "undefined" || document.visibilityState === "visible") return;
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    try {
        new Notification("DevPath — урок готов", {
            body: `${title}: ${body}`,
            tag: "devpath-lesson-ready",
        });
    } catch {
        /* ignore */
    }
}
