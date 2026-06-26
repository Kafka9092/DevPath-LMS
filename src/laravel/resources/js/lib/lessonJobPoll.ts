import axios from "axios";

export const KAFKA_GENERATING_STATUSES = new Set([
    "generating_content",
    "generating_chat",
    "generating_review",
    "generating_analyze",
]);

export interface KafkaJobPollData {
    status?: string;
    error?: boolean;
    message?: string;
    job_id?: string;
    success?: boolean;
    generated?: boolean;
    type?: string;
    is_correct?: boolean;
    [key: string]: unknown;
}

/** @deprecated use KafkaJobPollData */
export type LessonJobPollData = KafkaJobPollData;

/** @deprecated use KAFKA_GENERATING_STATUSES */
export const LESSON_GENERATING_STATUSES = KAFKA_GENERATING_STATUSES;

type PollHandle = { cancelled: boolean };

export function stopKafkaJobPoll(handleRef: { current: PollHandle | null }): void {
    if (handleRef.current) {
        handleRef.current.cancelled = true;
        handleRef.current = null;
    }
}

/** @deprecated use stopKafkaJobPoll */
export const stopLessonJobPoll = stopKafkaJobPoll;

export function shouldKeepKafkaJobPolling(data: KafkaJobPollData): boolean {
    return data.status === "pending";
}

/** @deprecated use shouldKeepKafkaJobPolling */
export const shouldKeepLessonJobPolling = shouldKeepKafkaJobPolling;

export async function pollKafkaJobOnce(jobId: string): Promise<KafkaJobPollData> {
    const res = await axios.post("/kafka/job", { job_id: jobId });
    return res.data as KafkaJobPollData;
}

/** @deprecated use pollKafkaJobOnce */
export const pollLessonJobOnce = pollKafkaJobOnce;

export function startKafkaJobPoll(
    jobId: string,
    handleRef: { current: PollHandle | null },
    handlers: {
        onDone: (data: KafkaJobPollData) => void;
        onFailed?: (data: KafkaJobPollData) => void;
    },
    initialDelayMs = 1500,
): void {
    stopKafkaJobPoll(handleRef);
    const handle: PollHandle = { cancelled: false };
    handleRef.current = handle;

    let delayMs = initialDelayMs;

    const run = async () => {
        if (handle.cancelled) {
            return;
        }

        try {
            const data = await pollKafkaJobOnce(jobId);

            if (handle.cancelled) {
                return;
            }

            if (shouldKeepKafkaJobPolling(data)) {
                window.setTimeout(run, delayMs);
                delayMs = Math.min(Math.round(delayMs * 1.2), 8000);
                return;
            }

            if (data.status === "failed" || data.error) {
                handlers.onFailed?.(data);
            } else {
                handlers.onDone(data);
            }

            stopKafkaJobPoll(handleRef);
        } catch {
            if (!handle.cancelled) {
                window.setTimeout(run, Math.min(delayMs * 1.5, 10000));
                delayMs = Math.min(Math.round(delayMs * 1.2), 8000);
            }
        }
    };

    void run();
}

/** @deprecated use startKafkaJobPoll */
export const startLessonJobPoll = startKafkaJobPoll;

export function waitForKafkaJob(jobId: string, timeoutMs = 120_000): Promise<KafkaJobPollData> {
    const started = Date.now();
    let delayMs = 1500;

    return new Promise((resolve, reject) => {
        const run = async () => {
            try {
                const data = await pollKafkaJobOnce(jobId);

                if (data.status === "pending") {
                    if (Date.now() - started > timeoutMs) {
                        reject(new Error("Генерация заняла слишком много времени"));
                        return;
                    }

                    window.setTimeout(run, delayMs);
                    delayMs = Math.min(Math.round(delayMs * 1.2), 8000);
                    return;
                }

                if (data.status === "failed" || data.error) {
                    reject(new Error(typeof data.message === "string" ? data.message : "Ошибка генерации"));
                    return;
                }

                resolve(data);
            } catch (error) {
                reject(error);
            }
        };

        void run();
    });
}

/** @deprecated use waitForKafkaJob */
export const waitForLessonJob = waitForKafkaJob;
