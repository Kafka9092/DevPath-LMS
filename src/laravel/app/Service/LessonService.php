<?php

namespace App\Service;

use App\Models\Course;
use App\Models\Subtopic;
use App\Models\UserLearningProfile;
use App\Models\UserProgressSubtopic;
use App\Service\Kafka\KafkaJobResolver;
use App\Service\Kafka\LessonKafkaService;
use Illuminate\Support\Facades\Log;


class LessonService
{
    public function __construct(
        protected OllamaService $ollama,
        protected SonarQubeService $sonar,
        protected UserLearningProfileService $profileService,
        protected UserBehaviorService $behaviorService,
        protected LessonContextBuilder $contextBuilder,
        protected LessonCompetenceRecorder $competenceRecorder,
        protected LessonKafkaService $lessonKafka,
        protected KafkaJobResolver $kafkaJobs,
        protected LessonMentorConductService $mentorConduct,
        protected LessonTaskFallbackBuilder $taskFallback,
    ) {}


    public function startLesson(int $userId, int $courseId, int $subtopicId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = $this->resolveProgress($userId, $courseId, $subtopicId, $profile);
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        if ($progress->is_completed) {
            $slides = $this->resolveTheorySlides($progress, $subtopic);
            if ($slides !== []) {
                return $this->theoryReviewSlideAt($subtopic, $progress, $slides, 0);
            }

            return $this->formatCompletedLesson($progress);
        }

        $progress = $this->ensureLessonContentReady($subtopic, $profile, $progress, $ctx);

        if ($this->shouldSkipTheoryForStrongSkill($ctx, $progress)) {
            return $this->openLessonWithPracticeAfterStrongSkill($subtopic, $profile, $progress, $ctx);
        }

        return $this->openLessonAtCurrentProgress($subtopic, $profile, $progress, $ctx);
    }

    private function shouldSkipTheoryForStrongSkill(array $ctx, UserProgressSubtopic $progress): bool
    {
        if ($progress->theory_complete || (int) $progress->theory_part_index > 0) {
            return false;
        }

        if (! ($ctx['has_cat'] ?? false)) {
            return false;
        }

        return ($ctx['subtopic_skill'] ?? 'neutral') === 'strong';
    }

    private function openLessonWithPracticeAfterStrongSkill(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): array {
        $state = $this->getTheoryState($progress);
        $intro = $this->taskIntroFromCtx($ctx);
        $state['skipped_theory'] = true;
        if ($intro !== null) {
            $state['skip_intro'] = $intro;
        }

        $slidesCount = count($state['slides'] ?? []);

        $progress->update([
            'theory_complete'   => true,
            'theory_part_index' => $slidesCount,
            'generated_theory'  => json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);

        $progress = $progress->fresh();

        if ($this->isStructuredTaskCached($progress, $ctx)) {
            return $this->taskBlockFromProgress($progress, $ctx['direction'], $ctx);
        }

        return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx, $intro);
    }

    private function openLessonAtCurrentProgress(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): array {
        if ($progress->theory_complete) {
            if ($this->shouldReopenTheoryAfterIncorrectSkip($progress)) {
                $progress->update([
                    'theory_complete'   => false,
                    'theory_part_index' => 0,
                ]);

                return $this->serveTheoryFromCache($subtopic, $progress->fresh(), $this->getTheoryState($progress->fresh()));
            }

            return $this->taskBlockFromProgress($progress, $ctx['direction'], $ctx);
        }

        $state = $this->getTheoryState($progress);
        if (! empty($state['slides'])) {
            $idx = (int) $progress->theory_part_index;
            if ($idx >= count($state['slides'])) {
                return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
            }

            return $this->serveTheoryFromCache($subtopic, $progress, $state);
        }

        if (! empty($state['chunks'])) {
            $idx = (int) $progress->theory_part_index;
            if ($idx >= count($state['chunks'])) {
                return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
            }

            $total = (int) ($state['total_parts'] ?? $ctx['theory_parts'] ?? 3);

            return $this->formatTheorySlide([
                'content'  => $state['chunks'][$idx],
                'has_more' => ($idx + 1) < $total,
            ], $idx + 1, $total, $subtopic->title, $state['personalization_label'] ?? null);
        }

        return $this->generateTheoryBlock($subtopic, $profile, $progress, $ctx);
    }

    private function shouldReopenTheoryAfterIncorrectSkip(UserProgressSubtopic $progress): bool
    {
        if ((int) $progress->theory_part_index > 0) {
            return false;
        }

        $state = $this->getTheoryState($progress);
        if (! empty($state['skipped_theory'])) {
            return false;
        }

        return ! empty($state['slides']);
    }

    private function ensureLessonContentReady(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): UserProgressSubtopic {
        if ($this->isSubtopicContentReady($progress)) {
            return $progress;
        }

        $subtopic->loadMissing('theme.module.course');
        $course = $subtopic->theme->module->course;

        if (! $this->ollama->isAvailable()) {
            throw new \RuntimeException(
                'Урок ещё не сгенерирован. Нажмите «Начать урок» и дождитесь завершения.',
                422,
            );
        }

        try {
            $this->pregenerateSubtopicContent($subtopic, $profile, $progress, $ctx);
        } catch (\Throwable $e) {
            Log::warning('ensureLessonContentReady failed: ' . $e->getMessage());
        }

        $progress = $progress->fresh();

        if (! $this->isSubtopicContentReady($progress)) {
            throw new \RuntimeException(
                'Не удалось подготовить урок. Нажмите «Начать урок» ещё раз.',
                422,
            );
        }

        return $progress;
    }

    public function getLessonStatus(int $userId, int $courseId, int $subtopicId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->first();

        if (!$progress) {
            return [
                'generated'               => false,
                'completed'               => false,
                'theory_complete'         => false,
                'theory_part_index'       => 0,
                'in_progress'             => false,
                'in_progress_subtopic_id' => $this->findInProgressSubtopicId($userId, $courseId),
                'draft_code'              => null,
                'theory_available'        => false,
                'mentor_chat_blocked'     => false,
            ];
        }

        $generated = $this->isSubtopicContentReady($progress);

        $subtopic = Subtopic::find($subtopicId);

        return [
            'generated'                => $generated,
            'completed'                => (bool) $progress->is_completed,
            'theory_complete'          => (bool) $progress->theory_complete,
            'theory_part_index'        => (int) $progress->theory_part_index,
            'in_progress'              => $generated && ((int) $progress->theory_part_index > 0 || $progress->theory_complete),
            'in_progress_subtopic_id'  => $this->findInProgressSubtopicId($userId, $courseId),
            'draft_code'               => $progress->submitted_code,
            'theory_available'         => $subtopic
                ? $this->resolveTheorySlides($progress, $subtopic) !== []
                : false,
            'mentor_chat_blocked'      => $this->mentorConduct->isChatBlocked($progress),
            ...$this->contentGenerationStatus($userId, $courseId, $subtopicId, $generated),
        ];
    }

    public function getTaskFunctionSignature(int $userId, int $courseId, int $subtopicId): ?string
    {
        $this->assertCourseAccess($userId, $courseId);

        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->first();

        if (! $progress) {
            return null;
        }

        $state = $this->getTheoryState($progress);
        $saved = is_array($state['task'] ?? null) ? $state['task'] : [];
        $signature = trim((string) ($saved['function_signature'] ?? ''));

        return $signature !== '' ? $signature : null;
    }

    public function pollJob(string $jobId): array
    {
        return $this->kafkaJobs->poll($jobId);
    }

 
    private function contentGenerationStatus(int $userId, int $courseId, int $subtopicId, bool $generated): array
    {
        if ($generated) {
            return [];
        }

        $jobId = $this->lessonKafka->getContentJobId($userId, $courseId, $subtopicId);
        if ($jobId === null) {
            return [];
        }

        $result = $this->lessonKafka->peekResult($jobId);
        if ($result !== null && empty($result['error'])) {
            return [
                'status' => 'ready',
                'job_id' => $jobId,
            ];
        }

        if ($result !== null && ! empty($result['error'])) {
            return [
                'status'    => 'failed',
                'job_id'    => $jobId,
                'generated' => false,
                'message'   => (string) ($result['message'] ?? 'Ошибка генерации урока'),
            ];
        }

        return [
            'status'    => 'generating_content',
            'job_id'    => $jobId,
            'generated' => false,
        ];
    }

    public function theoryReviewSlide(int $userId, int $courseId, int $subtopicId, int $slideIndex = 0): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course')->findOrFail($subtopicId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();

        $slides = $this->resolveTheorySlides($progress, $subtopic);
        if ($slides === []) {
            throw new \RuntimeException('Теория для этого урока не сохранена в базе.', 404);
        }

        if ($slideIndex >= count($slides)) {
            if ($progress->is_completed) {
                return $this->formatCompletedLesson($progress);
            }

            throw new \RuntimeException('Слайд теории не найден.', 404);
        }

        return $this->theoryReviewSlideAt($subtopic, $progress, $slides, $slideIndex);
    }

    private function findInProgressSubtopicId(int $userId, int $courseId): ?int
    {
        $candidates = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('is_completed', false)
            ->where(function ($q) {
                $q->where('theory_complete', true)
                    ->orWhere('theory_part_index', '>', 0);
            })
            ->orderByDesc('updated_at')
            ->get();

        foreach ($candidates as $progress) {
            if (! $this->isSubtopicContentReady($progress)) {
                continue;
            }

            return (int) $progress->subtopic_id;
        }

        return null;
    }


    public function generateLesson(int $userId, int $courseId, int $subtopicId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = $this->resolveProgress($userId, $courseId, $subtopicId, $profile);
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        if ($progress->is_completed) {
            return ['status' => 'completed', 'generated' => true, 'from_cache' => true];
        }

        $fromCache = $this->isSubtopicContentReady($progress);

        if (! $fromCache) {
            $state     = $this->getTheoryState($progress);
            $hasSlides = ! empty($state['slides']) && ! empty($state['pregenerated']);

            if ($hasSlides && ! $this->isStructuredTaskCached($progress, $ctx)) {
                try {
                    $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
                } catch (\Throwable $e) {
                    Log::warning('Task repair during generateLesson failed: ' . $e->getMessage());
                }

                $progress  = $progress->fresh();
                $fromCache = $this->isSubtopicContentReady($progress);

                if ($fromCache) {
                    $state = $this->getTheoryState($progress);

                    return [
                        'generated'    => true,
                        'from_cache'   => false,
                        'slides_count' => count($state['slides'] ?? []),
                    ];
                }
            }

            if ($this->lessonKafka->useKafkaFor('content')) {
                $existingJobId = $this->lessonKafka->getContentJobId($userId, $courseId, $subtopicId);
                if ($existingJobId !== null) {
                    return [
                        'status'    => 'generating_content',
                        'job_id'    => $existingJobId,
                        'generated' => false,
                    ];
                }

                $jobId = $this->lessonKafka->publish(
                    $this->lessonKafka->topic('content_generate'),
                    [
                        'user_id'     => $userId,
                        'course_id'   => $courseId,
                        'subtopic_id' => $subtopicId,
                    ],
                    "{$userId}:{$courseId}:{$subtopicId}",
                );

                $this->lessonKafka->rememberContentJob($userId, $courseId, $subtopicId, $jobId);

                return [
                    'status'    => 'generating_content',
                    'job_id'    => $jobId,
                    'generated' => false,
                ];
            }

            if (! $this->ollama->isAvailable()) {
                throw new \RuntimeException(
                    'AI недоступен. Проверьте Ollama и повторите генерацию.',
                    503
                );
            }

            $progress = $this->prepareFullLessonIfNeeded($subtopic, $profile, $progress, $ctx);

            if (! $this->isSubtopicContentReady($progress)) {
                throw new \RuntimeException('Не удалось сгенерировать урок. Попробуйте ещё раз.', 500);
            }
        }

        $state = $this->getTheoryState($progress);

        return [
            'generated'    => true,
            'from_cache'   => $fromCache,
            'slides_count' => count($state['slides'] ?? []),
        ];
    }

    
    private function prepareFullLessonIfNeeded(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): UserProgressSubtopic {
        if ($this->isSubtopicContentReady($progress)) {
            return $progress;
        }

        if (!$this->ollama->isAvailable()) {
            return $progress;
        }

        try {
            $this->pregenerateSubtopicContent($subtopic, $profile, $progress, $ctx);
        } catch (\Throwable $e) {
            Log::warning('Full lesson generation failed: ' . $e->getMessage());
        }

        return $progress->fresh();
    }

    
    public function pregenerateCourseContent(int $userId, int $courseId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $course  = Course::with('modules.themes.subtopics')->findOrFail($courseId);
        $profile = $this->profileService->getProfile($userId, $courseId);
        $ctx     = $this->contextBuilder->forCourse($userId, $course);

        $total = 0;
        $done  = 0;

        foreach ($course->modules as $module) {
            foreach ($module->themes as $theme) {
                foreach ($theme->subtopics->sortBy('order') as $subtopic) {
                    $total++;
                    $progress = $this->resolveProgress($userId, $courseId, $subtopic->id, $profile);

                    if ($this->isSubtopicContentReady($progress)) {
                        $done++;
                        continue;
                    }

                    try {
                        $this->pregenerateSubtopicContent($subtopic, $profile, $progress, $ctx);
                        $done++;
                    } catch (\Throwable $e) {
                        Log::error('Pregenerate subtopic failed', [
                            'subtopic_id' => $subtopic->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return ['total' => $total, 'ready' => $done];
    }

    public function getCourseContentStatus(int $userId, int $courseId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $course = Course::with('modules.themes.subtopics')->findOrFail($courseId);
        $total  = 0;
        $ready  = 0;

        foreach ($course->modules as $module) {
            foreach ($module->themes as $theme) {
                foreach ($theme->subtopics as $subtopic) {
                    $total++;
                    $progress = UserProgressSubtopic::where('user_id', $userId)
                        ->where('course_id', $courseId)
                        ->where('subtopic_id', $subtopic->id)
                        ->first();

                    if ($progress && $this->isSubtopicContentReady($progress)) {
                        $ready++;
                    }
                }
            }
        }

        return [
            'total'   => $total,
            'ready'   => $ready,
            'complete' => $total > 0 && $ready >= $total,
        ];
    }

    public function saveDraftCode(int $userId, int $courseId, int $subtopicId, string $code): void
    {
        $this->assertCourseAccess($userId, $courseId);

        UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->where('is_completed', false)
            ->update(['submitted_code' => $code]);
    }

    public function continueLesson(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $action,
        ?string $message = null,
        ?string $currentCode = null,
    ): array {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        return match ($action) {
            'continue'       => $this->handleContinue($subtopic, $profile, $progress, $ctx),
            'question'       => $this->dispatchQuestion($userId, $courseId, $subtopicId, $subtopic, $profile, $progress, $ctx, $message ?? '', $currentCode),
            'request_theory' => $this->returnToTheory($subtopic, $progress),
            'theory_back'    => $this->retreatTheoryFromCache($subtopic, $progress),
            default          => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    public function generateLessonContentSync(int $userId, int $courseId, int $subtopicId): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = $this->resolveProgress($userId, $courseId, $subtopicId, $profile);
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        if ($progress->is_completed || $this->isSubtopicContentReady($progress)) {
            $state = $this->getTheoryState($progress);

            return [
                'generated'    => true,
                'from_cache'   => true,
                'slides_count' => count($state['slides'] ?? []),
            ];
        }

        if (! $this->ollama->isAvailable()) {
            $state = $this->getTheoryState($progress);
            if (empty($state['slides']) && empty($state['chunks'])) {
                return ['error' => true, 'message' => 'AI недоступен'];
            }

            try {
                $this->pregenerateSubtopicContent($subtopic, $profile, $progress, $ctx);
            } catch (\Throwable $e) {
                Log::warning('Lesson task fallback failed: ' . $e->getMessage());

                return ['error' => true, 'message' => 'Не удалось сгенерировать урок'];
            }

            $progress = $progress->fresh();

            if ($this->isSubtopicContentReady($progress)) {
                $state = $this->getTheoryState($progress);

                return [
                    'generated'    => true,
                    'from_cache'   => false,
                    'slides_count' => count($state['slides'] ?? []),
                ];
            }

            return ['error' => true, 'message' => 'AI недоступен'];
        }

        try {
            $this->pregenerateSubtopicContent($subtopic, $profile, $progress, $ctx);
        } catch (\Throwable $e) {
            Log::warning('Kafka lesson content generation failed: ' . $e->getMessage());

            return ['error' => true, 'message' => 'Не удалось сгенерировать урок'];
        }

        $progress = $progress->fresh();

        if (! $this->isSubtopicContentReady($progress)) {
            return ['error' => true, 'message' => 'Не удалось сгенерировать урок'];
        }

        $state = $this->getTheoryState($progress);

        return [
            'generated'    => true,
            'from_cache'   => false,
            'slides_count' => count($state['slides'] ?? []),
        ];
    }

    public function handleQuestionSync(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $message,
        ?string $currentCode = null,
        bool $applyConduct = true,
    ): array {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();
        $ctx = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        if ($applyConduct) {
            $conductResponse = $this->resolveMentorConductResponse($subtopic, $progress, $message);
            if ($conductResponse !== null) {
                return $conductResponse;
            }
        }

        return $this->handleQuestion($subtopic, $profile, $progress, $ctx, $message, $currentCode);
    }

    public function requestHintSync(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $hintType,
        ?string $currentCode = null,
    ): array {
        return $this->requestHintInternal($userId, $courseId, $subtopicId, $hintType, $currentCode);
    }

    private function dispatchQuestion(
        int $userId,
        int $courseId,
        int $subtopicId,
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
        string $message,
        ?string $currentCode,
    ): array {
        $conductResponse = $this->resolveMentorConductResponse($subtopic, $progress, $message);
        if ($conductResponse !== null) {
            return $conductResponse;
        }

        if ($this->lessonKafka->useKafkaFor('chat')) {
            $jobId = $this->lessonKafka->publish(
                $this->lessonKafka->topic('mentor_chat'),
                [
                    'operation'    => 'question',
                    'user_id'      => $userId,
                    'course_id'    => $courseId,
                    'subtopic_id'  => $subtopicId,
                    'message'      => $message,
                    'current_code' => $currentCode,
                ],
                "{$userId}:{$subtopicId}",
            );

            return [
                'status' => 'generating_chat',
                'job_id' => $jobId,
            ];
        }

        return $this->handleQuestion($subtopic, $profile, $progress, $ctx, $message, $currentCode);
    }

    public function requestHint(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $hintType,
        ?string $currentCode = null,
    ): array {
        if ($this->lessonKafka->useKafkaFor('chat')) {
            $jobId = $this->lessonKafka->publish(
                $this->lessonKafka->topic('mentor_chat'),
                [
                    'operation'    => 'hint',
                    'user_id'      => $userId,
                    'course_id'    => $courseId,
                    'subtopic_id'  => $subtopicId,
                    'hint_type'    => $hintType,
                    'current_code' => $currentCode,
                ],
                "{$userId}:{$subtopicId}",
            );

            return [
                'status' => 'generating_chat',
                'job_id' => $jobId,
            ];
        }

        return $this->requestHintInternal($userId, $courseId, $subtopicId, $hintType, $currentCode);
    }

    private function requestHintInternal(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $hintType,
        ?string $currentCode = null,
    ): array {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course')->findOrFail($subtopicId);
        $course   = $subtopic->theme->module->course;
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        $progress->increment('hints_used');

        $taskDesc = $progress->task_description ?? $subtopic->task ?? $subtopic->title;
        $persona  = $profile?->mentor_persona ?? 'colleague';
        $direction = $ctx['direction'];

        if (!$this->ollama->isAvailable()) {
            return $this->fallbackHint($hintType, $taskDesc);
        }

        if ($hintType === 'plantuml') {
            $hintType = 'code_snippet';
        }

        $typeLabel = match ($hintType) {
            'code_snippet'      => 'фрагмент похожего кода (не полное решение)',
            'explanation'       => 'объяснение подхода шаг за шагом',
            'simpler_solution'  => 'упрощённый план без готового кода',
            'skeleton_hint'     => 'каркас решения: сигнатуры функций, TODO-комментарии, структура — БЕЗ готовой логики и БЕЗ полного решения',
            default             => 'краткая подсказка',
        };

        $codeSection = $this->editorCodePromptSection($currentCode, $direction);

        $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personaPrompt($ctx)}
Подтема: «{$subtopic->title}»
Задача: {$taskDesc}
{$this->contextBuilder->personalizationPrompt($ctx)}

{$this->contextBuilder->mentorDialogRules()}
{$codeSection}

Дай подсказку типа: {$typeLabel}.
Опирайся на реальный код студента: укажи конкретные ошибки или пробелы, не общие советы. Без номеров строк.
НЕ давай полное готовое решение.

Верни JSON:
{
  "hint_type": "{$hintType}",
  "title": "краткий заголовок",
  "content": "текст подсказки (для code_snippet и skeleton_hint — фрагмент кода в markdown)"
}
PROMPT;

        try {
            $data = $this->ollama->generateJson($prompt);

            return [
                'type'      => 'hint',
                'hint_type' => $data['hint_type'] ?? $hintType,
                'title'     => $data['title'] ?? 'Подсказка',
                'content'   => $data['content'] ?? 'Попробуйте разбить задачу на шаги.',
            ];
        } catch (\Exception $e) {
            Log::error('Hint generation failed: ' . $e->getMessage());

            return $this->fallbackHint($hintType, $taskDesc);
        }
    }

    /** Студент нажал «Отправить решение» — Sonar, потом AI-разбор. */
    public function submitSolution(int $userId, int $courseId, int $subtopicId, string $code): array
    {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $course   = Course::with('modules.themes.subtopics')->findOrFail($courseId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();
        $ctx      = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        $projectKey  = $this->sonar->saveCodeSnippet($code);
        $sonarResult = $this->sonar->analyzeProject($projectKey);

        if ($this->lessonKafka->useKafkaFor('review')) {
            $jobId = $this->lessonKafka->publish(
                $this->lessonKafka->topic('practice_review'),
                [
                    'user_id'      => $userId,
                    'course_id'    => $courseId,
                    'subtopic_id'  => $subtopicId,
                    'code'         => $code,
                    'sonar_result' => $sonarResult,
                ],
                "{$userId}:{$subtopicId}",
            );

            return [
                'status' => 'generating_review',
                'job_id' => $jobId,
            ];
        }

        return $this->completeSubmitAfterInfrastructure(
            $userId,
            $courseId,
            $subtopicId,
            $code,
            $subtopic,
            $profile,
            $course,
            $progress,
            $ctx,
            $sonarResult,
        );
    }


    public function completeSubmitAfterInfrastructureSync(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $code,
        array $sonarResult,
    ): array {
        $this->assertCourseAccess($userId, $courseId);

        $subtopic = Subtopic::with('theme.module.course.direction', 'theme.module.course.level')->findOrFail($subtopicId);
        $profile  = $this->profileService->getProfile($userId, $courseId);
        $course   = Course::with('modules.themes.subtopics')->findOrFail($courseId);
        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('subtopic_id', $subtopicId)
            ->firstOrFail();
        $ctx = $this->contextBuilder->forCourse($userId, $course, $subtopic->title);

        return $this->completeSubmitAfterInfrastructure(
            $userId,
            $courseId,
            $subtopicId,
            $code,
            $subtopic,
            $profile,
            $course,
            $progress,
            $ctx,
            $sonarResult,
        );
    }

    private function completeSubmitAfterInfrastructure(
        int $userId,
        int $courseId,
        int $subtopicId,
        string $code,
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        Course $course,
        UserProgressSubtopic $progress,
        array $ctx,
        array $sonarResult,
    ): array {
        $aiReview = $this->reviewCodeWithAi($subtopic, $profile, $ctx, $code, $sonarResult);

        $lenient   = (bool) config('services.lesson.lenient_review', false);
        $passScore = $lenient ? 50 : 60;

        $score     = (int) ($aiReview['score'] ?? 0);
        $isCorrect = ($aiReview['is_correct'] ?? false) && $score >= $passScore;

        if ($this->sonar->hasBlockingIssues($sonarResult['issues']) && ! $lenient) {
            $isCorrect = false;
        }

        if ($lenient && trim($code) !== '' && $score >= 50) {
            $isCorrect = true;
            $score = max($score, 80);
            $aiReview['feedback'] = ($aiReview['is_correct'] ?? false)
                ? ($aiReview['feedback'] ?? 'Решение принято.')
                : 'Решение принято. Код соответствует условию задачи.';
        }

        $review         = is_array($progress->lesson_review) ? $progress->lesson_review : [];
        $tasksRequired  = (int) ($ctx['tasks_required'] ?? 1);
        $tasksCompleted = (int) ($review['tasks_completed'] ?? 0);
        $failedAttempts = (int) ($review['failed_attempts'] ?? 0);

        $microTask = null;
        if (!$isCorrect) {
            $failedAttempts++;
            if ($failedAttempts >= 2 && empty($review['micro_task_done'])) {
                $microTask = $this->generateMicroTask($subtopic, $profile, $ctx, $aiReview);
            }
        }

        $progress->update([
            'submitted_code'  => $code,
            'lesson_score'    => $score,
            'lesson_feedback' => $aiReview['feedback'] ?? null,
            'lesson_review'   => array_merge($review, [
                'strengths'       => $aiReview['strengths'] ?? [],
                'improvements'    => $aiReview['improvements'] ?? [],
                'is_correct'      => $isCorrect,
                'failed_attempts' => $failedAttempts,
                'tasks_required'  => $tasksRequired,
            ]),
        ]);

        if ($microTask) {
            $progress->update([
                'lesson_review' => array_merge($progress->fresh()->lesson_review ?? [], ['micro_task_done' => true]),
            ]);

            return array_merge($this->buildSubmitResponse(
                false, $score, $aiReview, $sonarResult, null, null
            ), ['micro_task' => $microTask]);
        }

        if ($isCorrect) {
            $tasksCompleted++;
            $review['tasks_completed'] = $tasksCompleted;
            $progress->update(['lesson_review' => array_merge($progress->lesson_review ?? [], $review)]);

            if ($tasksCompleted < $tasksRequired) {
                $followUp = $this->buildFollowUpTask($subtopic, $profile, $progress, $ctx, $tasksCompleted);
                $this->behaviorService->recordSuccess($userId, $courseId);

                return array_merge($this->buildSubmitResponse(
                    false, $score, $aiReview, $sonarResult, null, null
                ), [
                    'partial_pass'    => true,
                    'tasks_completed' => $tasksCompleted,
                    'tasks_required'  => $tasksRequired,
                    'next_task'       => $followUp,
                    'feedback'        => ($aiReview['feedback'] ?? '') . " Задача {$tasksCompleted}/{$tasksRequired} решена. Следующая — посложнее.",
                ]);
            }

            $progress->markAsCompleted();
            $this->updateCourseProgress($userId, $course);
            $this->behaviorService->recordSuccess($userId, $courseId);
            $this->competenceRecorder->recordLessonOutcome(
                $userId,
                $courseId,
                $subtopic,
                $code,
                array_merge($aiReview, ['is_correct' => true]),
            );

            return $this->buildSubmitResponse(
                true, $score, $aiReview, $sonarResult,
                $this->findNextSubtopic($course, $subtopicId),
                $this->formatHistoryItem($progress->fresh('subtopic.theme')),
            );
        }

        $this->behaviorService->recordFailure($userId, $courseId);

        return $this->buildSubmitResponse(
            false, $score, $aiReview, $sonarResult, null, null
        );
    }

    private function buildSubmitResponse(
        bool $isCorrect,
        int $score,
        array $aiReview,
        array $sonarResult,
        ?array $nextSubtopic,
        ?array $historyItem,
    ): array {
        return [
            'is_correct'    => $isCorrect,
            'score'         => $score,
            'feedback'      => $aiReview['feedback'] ?? ($isCorrect ? 'Отличная работа!' : 'Есть ошибки — попробуйте исправить.'),
            'strengths'     => $aiReview['strengths'] ?? [],
            'improvements'  => $aiReview['improvements'] ?? [],
            'sonar'         => [
                'metrics' => $sonarResult['metrics'],
                'issues'  => array_slice($sonarResult['issues'], 0, $isCorrect ? 10 : 15),
            ],
            'next_subtopic' => $nextSubtopic,
            'history_item'  => $historyItem,
        ];
    }

    private function generateMicroTask(Subtopic $subtopic, ?UserLearningProfile $profile, array $ctx, array $aiReview): ?array
    {
        if (!$this->ollama->isAvailable()) {
            return [
                'type'         => 'micro_task',
                'title'        => 'Разминка: ' . $subtopic->title,
                'description'  => 'Короткая разминка по подтеме «' . $subtopic->title . '» на простом примере, затем вернитесь к основной задаче.',
                'starter_code' => $this->starterCodeFor($ctx['direction']),
            ];
        }

        $weak = implode(', ', array_slice($ctx['weak_topics'] ?? [], 0, 3));
        $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
Студент дважды ошибся в основной задаче по подтеме «{$subtopic->title}».
Слабые темы: {$weak}
Ошибки: {$this->formatImprovements($aiReview)}

Сгенерируй микро-задачу на 2 минуты строго по подтеме «{$subtopic->title}» и языку «{$ctx['direction']}».
Заголовок (title) должен явно отражать «{$subtopic->title}» — не уходи в другие темы (например, «формат числа», если урок про if/else).
Тот же концепт, что в основной задаче, но проще и с другой формулировкой.
{$this->contextBuilder->taskDomainPrompt($ctx)}
{$this->contextBuilder->taskToneRules($ctx)}
{$this->contextBuilder->taskBriefRules(array_merge($ctx, ['task_difficulty' => 'beginner']))}
{$this->contextBuilder->taskJsonSchema(array_merge($ctx, ['task_difficulty' => 'beginner']))}
{$this->contextBuilder->taskValidationReminder(array_merge($ctx, ['task_difficulty' => 'beginner']))}
PROMPT;

        try {
            $block = $this->generateStructuredTaskBlock(
                $prompt,
                $subtopic,
                array_merge($ctx, ['task_difficulty' => 'beginner']),
                'Микро-задача',
            );
            $normalized = $this->normalizeTaskPayload($block, $ctx['direction'], 'Микро-задача');

            return [
                'type'                 => 'micro_task',
                'title'                => $normalized['title'],
                'action'               => $normalized['action'],
                'function_signature'   => $normalized['function_signature'],
                'description'          => $normalized['description'] ?: 'Короткая разминка перед основной задачей.',
                'starter_code'         => $normalized['starter_code'],
                'constraints'          => $normalized['constraints'],
                'example_input'        => $normalized['example_input'],
                'example_output'       => $normalized['example_output'],
                'test_cases'           => $normalized['test_cases'],
            ];
        } catch (\Exception $e) {
            Log::error('Micro task generation failed: ' . $e->getMessage());

            return null;
        }
    }

    private function buildFollowUpTask(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
        int $taskIndex,
    ): array {
        $direction = $ctx['direction'];
        $diff      = $this->resolveFollowUpDifficulty($ctx, $taskIndex);

        if (!$this->ollama->isAvailable()) {
            $block = [
                'type'         => 'task',
                'title'        => $subtopic->title . ' — часть ' . ($taskIndex + 1),
                'description'  => 'Усложнённый вариант задачи по теме «' . $subtopic->title . '».',
                'starter_code' => $this->starterCodeFor($direction),
            ];
            $this->persistTask($progress, $block);

            return $block;
        }

        $prevDesc = $progress->task_description ?? '';
        $partNum  = $taskIndex + 1;
        $prompt   = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
{$this->contextBuilder->taskDomainPrompt($ctx)}
{$this->contextBuilder->taskToneRules($ctx)}

Студент решил первую задачу по «{$subtopic->title}». Дай СЛЕДУЮЩУЮ задачу (часть {$partNum}) — сложнее предыдущей.
Предыдущая задача: {$prevDesc}
{$this->contextBuilder->taskBriefRules(array_merge($ctx, ['task_difficulty' => $diff]))}
{$this->contextBuilder->taskJsonSchema(array_merge($ctx, ['task_difficulty' => $diff]))}
{$this->contextBuilder->taskValidationReminder(array_merge($ctx, ['task_difficulty' => $diff]))}
PROMPT;

        try {
            $block = $this->generateStructuredTaskBlock(
                $prompt,
                $subtopic,
                array_merge($ctx, ['task_difficulty' => $diff]),
                $subtopic->title . ' — часть ' . ($taskIndex + 1),
            );
            $this->persistTask($progress, $block);

            return $block;
        } catch (\Exception $e) {
            Log::error('Follow-up task failed: ' . $e->getMessage());
            $block = [
                'type'         => 'task',
                'title'        => $subtopic->title . ' — часть ' . ($taskIndex + 1),
                'description'  => 'Усложнённый вариант.',
                'starter_code' => $this->starterCodeFor($direction),
            ];
            $this->persistTask($progress, $block);

            return $block;
        }
    }

    private function formatImprovements(array $aiReview): string
    {
        return implode('; ', array_slice($aiReview['improvements'] ?? [], 0, 3));
    }

    private function formatCompletedLesson(UserProgressSubtopic $progress): array
    {
        $progress->loadMissing('subtopic.theme');

        return [
            'type'             => 'lesson_review',
            'content'          => 'Урок уже завершён. Ниже — ваша задача и решение.',
            'task_title'       => $progress->task_title ?? $progress->subtopic->title,
            'task_description' => $progress->task_description ?? $progress->subtopic->task,
            'submitted_code'   => $progress->submitted_code ?? '',
            'score'            => $progress->lesson_score,
            'feedback'         => $progress->lesson_feedback,
            'strengths'        => $progress->lesson_review['strengths'] ?? [],
            'improvements'     => $progress->lesson_review['improvements'] ?? [],
        ];
    }

    public function formatHistoryItem(UserProgressSubtopic $progress): array
    {
        $progress->loadMissing('subtopic.theme');

        return [
            'subtopic_id'       => $progress->subtopic_id,
            'subtopic_title'    => $progress->subtopic->title,
            'theme_title'       => $progress->subtopic->theme->title,
            'completed_at'      => $progress->completed_at?->format('d.m H:i') ?? '',
            'task_title'        => $progress->task_title,
            'task_description'  => $progress->task_description,
            'submitted_code'    => $progress->submitted_code,
            'lesson_score'      => $progress->lesson_score,
            'lesson_feedback'   => $progress->lesson_feedback,
        ];
    }

    private function handleContinue(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): array {
        if (! $progress->theory_complete) {
            $state = $this->getTheoryState($progress);
            if (! empty($state['slides'])) {
                return $this->advanceTheoryFromCache($subtopic, $profile, $progress, $ctx, $state);
            }

            return $this->generateTheoryBlock($subtopic, $profile, $progress, $ctx);
        }

        return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
    }

    private function resolveFollowUpDifficulty(array $ctx, int $taskIndex): string
    {
        $base = $ctx['task_difficulty'] ?? 'beginner';
        $order = ['beginner', 'junior_plus', 'middle'];

        $idx = array_search($base, $order, true);
        if ($idx === false) {
            $idx = 0;
        }

        $next = min(count($order) - 1, $idx + $taskIndex);

        return $order[$next];
    }

    private function resolveMentorConductResponse(
        Subtopic $subtopic,
        UserProgressSubtopic $progress,
        string $message,
    ): ?array {
        if ($this->mentorConduct->isChatBlocked($progress)) {
            return $this->formatMentorConductResponse(
                $progress,
                $this->mentorConduct->blockedResponse(),
            );
        }

        $conductResult = $this->mentorConduct->handleUserMessage(
            $progress,
            $subtopic->title,
            $message,
        );

        if (($conductResult['action'] ?? LessonMentorConductService::ACTION_CONTINUE)
            !== LessonMentorConductService::ACTION_CONTINUE) {
            return $this->formatMentorConductResponse($progress, $conductResult);
        }

        return null;
    }

    /** @param array{action: string, message?: string, mentor_chat_blocked?: bool} $conductResult */
    private function formatMentorConductResponse(
        UserProgressSubtopic $progress,
        array $conductResult,
    ): array {
        return [
            'type'                => 'answer',
            'content'             => (string) ($conductResult['message'] ?? ''),
            'has_more'            => ! $progress->theory_complete,
            'conduct'             => $conductResult['action'],
            'mentor_chat_blocked' => (bool) ($conductResult['mentor_chat_blocked'] ?? false),
        ];
    }

    private function handleQuestion(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
        string $message,
        ?string $currentCode = null,
    ): array {
        if (!$this->ollama->isAvailable()) {
            return [
                'type'     => 'answer',
                'content'  => 'ИИ временно недоступен. Попробуйте позже.',
                'has_more' => !$progress->theory_complete,
            ];
        }

        $direction = $ctx['direction'] ?? 'TypeScript';
        $codeSection = $this->editorCodePromptSection($currentCode, $direction);

        if ($progress->theory_complete) {
            $taskDesc = $progress->task_description ?? $subtopic->task ?? $subtopic->title;
            $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
Подтема: «{$subtopic->title}»
Практическая задача: {$taskDesc}

{$codeSection}

Вопрос студента: {$message}

{$this->contextBuilder->mentorDialogRules()}
Ответь на русском, кратко и по делу. Разбирай конкретные ошибки и неточности в коде студента выше.
Ссылайся на фрагменты логики, не на номера строк. Не выдавай полное готовое решение целиком.
Верни JSON: {"content": "ответ"}
PROMPT;
        } else {
            $state   = $this->getTheoryState($progress);
            $summary = $this->theorySummary($state);

            $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
Подтема: «{$subtopic->title}»
Уже объяснено: {$summary}
{$codeSection}
Вопрос студента: {$message}

{$this->contextBuilder->contentFormatRules()}
Ответь кратко по делу на русском. Не выдавай финальную практическую задачу.
Верни JSON: {"content": "ответ"}
PROMPT;
        }

        try {
            $data = $this->ollama->generateJson($prompt);
            $content = (string) ($data['content'] ?? 'Не удалось сформировать ответ.');

            if ($this->mentorConduct->breaksMentorRole($content)) {
                $content = $this->mentorConduct->inRoleFallbackMessage($subtopic->title);
            }

            return [
                'type'     => 'answer',
                'content'  => $content,
                'has_more' => !$progress->theory_complete,
            ];
        } catch (\Exception $e) {
            Log::error('Lesson question failed: ' . $e->getMessage());

            return [
                'type'     => 'answer',
                'content'  => 'Ошибка при генерации ответа.',
                'has_more' => !$progress->theory_complete,
            ];
        }
    }

    private function generateTheoryBlock(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): array {
        $state      = $this->getTheoryState($progress);
        $partIndex  = count($state['chunks']) + 1;
        $totalParts = $ctx['theory_parts'] ?? 3;
        $label      = $ctx['personalization_label'] ?? null;

        if ($partIndex > $totalParts) {
            $progress->update(['theory_complete' => true]);
            return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
        }

        $stored = $subtopic->theory;
        if ($stored && $partIndex === 1 && empty($state['chunks'])) {
            $slide = $this->formatTheorySlide([
                'content'  => $stored,
                'has_more' => $partIndex < $totalParts,
            ], $partIndex, $totalParts, $subtopic->title, $label);
            $this->saveTheoryChunk($progress, $slide['content'], !$slide['has_more']);

            return $slide;
        }

        if (!$this->ollama->isAvailable()) {
            $slide = $this->formatTheorySlide([
                'content'  => "Шаг {$partIndex} из {$totalParts}: {$subtopic->title}.",
                'has_more' => $partIndex < $totalParts,
            ], $partIndex, $totalParts, $subtopic->title, $label);
            $this->saveTheoryChunk($progress, $slide['content'], !$slide['has_more']);

            return $slide;
        }

        $summary = $this->theorySummary($state);
        $format  = $this->contextBuilder->contentFormatRules();
        $schema  = $this->contextBuilder->theorySlideJsonSchema($ctx['direction']);
        $sessionIntro = $partIndex === 1
            ? $this->contextBuilder->theorySessionIntro($ctx, $subtopic->title) . "\n\n"
            : '';

        $prompt  = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
{$this->contextBuilder->domainPrompt($ctx)}
{$this->contextBuilder->theoryDepthRules($ctx)}
{$format}

{$sessionIntro}Слайд {$partIndex} из {$totalParts} по теме «{$subtopic->title}».
Уже объяснено: {$summary}
Только этот слайд. Справа — фрагмент кода в playground (5–15 строк) или trace для пошаговой памяти. Схемы не используй.

{$schema}
mentor_opener только если partIndex=1 (сейчас {$partIndex}).
has_more = true если после этого слайда останутся части.
PROMPT;

        try {
            $data  = $this->ollama->generateJson($prompt);
            $slide = $this->formatTheorySlide($data, $partIndex, $totalParts, $subtopic->title, $label);
            $this->saveTheoryChunk($progress, $slide['content'], !$slide['has_more']);

            return $slide;
        } catch (\Exception $e) {
            Log::error('Theory generation failed: ' . $e->getMessage());
            $slide = $this->formatTheorySlide([
                'content' => "Шаг {$partIndex}: {$subtopic->title}",
            ], $partIndex, $totalParts, $subtopic->title, $label);
            $this->saveTheoryChunk($progress, $slide['content'], !$slide['has_more']);

            return $slide;
        }
    }

    private function getTheoryState(UserProgressSubtopic $progress): array
    {
        $raw = $progress->generated_theory;
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['chunks' => [(string) $raw]];
        }

        return $decoded;
    }

    private function resolveTheorySlides(UserProgressSubtopic $progress, Subtopic $subtopic): array
    {
        $state = $this->getTheoryState($progress);

        if (!empty($state['slides']) && is_array($state['slides'])) {
            return $state['slides'];
        }

        if (!empty($state['chunks'])) {
            return array_map(
                fn ($c) => is_array($c) ? $c : ['content' => (string) $c],
                $state['chunks'],
            );
        }

        if (!empty($subtopic->theory)) {
            return [[
                'content'      => (string) $subtopic->theory,
                'slide_title'  => $subtopic->title,
                'has_more'     => false,
            ]];
        }

        return [];
    }

    private function theoryReviewSlideAt(
        Subtopic $subtopic,
        UserProgressSubtopic $progress,
        array $slides,
        int $slideIndex,
    ): array {
        $total = count($slides);
        $slide = $slides[$slideIndex];
        $partIndex = $slideIndex + 1;

        $formatted = $this->formatTheorySlide(
            array_merge($slide, ['has_more' => $partIndex < $total]),
            $partIndex,
            $total,
            $subtopic->title,
        );
        $formatted['review_mode']       = true;
        $formatted['lesson_completed']  = (bool) $progress->is_completed;

        return $formatted;
    }

    private function theorySummary(array $state): string
    {
        $chunks = $state['chunks'] ?? [];
        if ($chunks === []) {
            return 'ничего';
        }

        $last = array_slice($chunks, -2);

        return mb_substr(implode(' | ', $last), 0, 600);
    }

    private function saveTheoryChunk(UserProgressSubtopic $progress, string $content, bool $complete): void
    {
        $state            = $this->getTheoryState($progress);
        $state['chunks'][] = $content;

        $progress->update([
            'generated_theory'  => json_encode($state, JSON_UNESCAPED_UNICODE),
            'theory_part_index' => count($state['chunks']),
            'theory_complete'   => $complete,
        ]);
    }

    private function formatTheorySlide(
        array $data,
        int $partIndex,
        int $totalParts,
        string $subtopicTitle,
        ?string $personalizationLabel = null,
    ): array {
        $data = $this->normalizeRightPanel($data);
        $hasMore = (bool) ($data['has_more'] ?? ($partIndex < $totalParts));
        if ($partIndex >= $totalParts) {
            $hasMore = false;
        }

        return [
            'type'                  => 'theory',
            'slide_title'           => $data['slide_title'] ?? "Шаг {$partIndex}: {$subtopicTitle}",
            'content'               => $data['content'] ?? '',
            'callout'               => $data['callout'] ?? null,
            'right_panel_mode'      => $data['right_panel_mode'] ?? 'none',
            'playground'            => $data['playground'] ?? null,
            'trace_steps'           => $data['trace_steps'] ?? null,
            'has_more'              => $hasMore,
            'part'                  => $partIndex,
            'total_parts'           => $totalParts,
            'personalization_label' => $partIndex === 1 ? $personalizationLabel : null,
            'mentor_message'        => $partIndex === 1
                ? ($data['mentor_opener'] ?? "Привет! Сегодня разберём «{$subtopicTitle}». Поехали по шагам.")
                : null,
        ];
    }

    private function normalizeRightPanel(array $data): array
    {
        $mode = $data['right_panel_mode'] ?? 'none';

        if ($mode === 'diagram' || !empty($data['diagram'])) {
            $mode = !empty($data['playground']['code']) ? 'playground' : 'none';
        }

        if ($mode === 'none' && !empty($data['playground']['code'])) {
            $mode = 'playground';
        }

        if ($mode === 'playground' && empty($data['playground']['code'])) {
            $mode = 'none';
        }

        if ($mode === 'trace' && empty($data['trace_steps'])) {
            $mode = 'none';
        }

        unset($data['diagram']);
        $data['right_panel_mode'] = $mode;

        return $data;
    }

    private function buildTaskBlock(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
        ?string $introMessage = null,
    ): array {
        $subtopic->loadMissing('theme.module.course');
        $course = $subtopic->theme->module->course;

        if ($this->isStructuredTaskCached($progress, $ctx)) {
            return $this->taskBlockFromProgress($progress, $ctx['direction'], $ctx);
        }

        $direction    = $ctx['direction'];
        $taskText     = $subtopic->task;

        if (! $this->ollama->isAvailable()) {
            $block = $this->formatTaskBlock(
                $this->taskFallback->build($ctx, $subtopic->title),
                $direction,
                $subtopic->title,
                $introMessage,
            );
            $this->persistTask($progress, $block);

            return $block;
        }

        $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
{$this->contextBuilder->taskDomainPrompt($ctx)}
{$this->contextBuilder->taskToneRules($ctx)}

Создай практическую задачу для «{$subtopic->title}» (курс «{$ctx['course_title']}»).
{$this->contextBuilder->taskBriefRules($ctx)}
{$this->contextBuilder->taskJsonSchema($ctx)}
{$this->contextBuilder->taskValidationReminder($ctx)}

Подсказка из программы: {$taskText}
PROMPT;

        try {
            $block = $this->generateStructuredTaskBlock($prompt, $subtopic, $ctx, $subtopic->title, $introMessage);
            $this->persistTask($progress, $block);

            return $block;
        } catch (\Exception $e) {
            Log::warning('Task AI generation failed, using structured fallback: ' . $e->getMessage());
            $block = $this->formatTaskBlock(
                $this->taskFallback->build($ctx, $subtopic->title),
                $direction,
                $subtopic->title,
                $introMessage,
            );
            $this->persistTask($progress, $block);

            return $block;
        }
    }

    private function taskBlockFromProgress(UserProgressSubtopic $progress, string $direction, array $ctx = []): array
    {
        $state = $this->getTheoryState($progress);
        $saved = is_array($state['task'] ?? null) ? $state['task'] : [];

        $block = [
            'type'                 => 'task',
            'title'                => $progress->task_title,
            'action'               => $saved['action'] ?? null,
            'function_signature'   => $saved['function_signature'] ?? null,
            'description'          => $progress->task_description,
            'starter_code'         => $saved['starter_code'] ?? $this->starterCodeFor($direction),
            'constraints'          => $saved['constraints'] ?? [],
            'example_input'        => $saved['example_input'] ?? '',
            'example_output'       => $saved['example_output'] ?? '',
            'test_cases'           => $saved['test_cases'] ?? [],
            'exam_block'           => $saved['exam_block'] ?? false,
            'exam_language'        => $saved['exam_language'] ?? null,
            'file_label'           => $saved['file_label'] ?? null,
            'commit_hint'          => $saved['commit_hint'] ?? null,
        ];

        if (! empty($state['skipped_theory'])) {
            $block['intro_message'] = $state['skip_intro'] ?? $this->taskIntroFromCtx($ctx);
        }

        return $block;
    }

    private function taskIntroFromCtx(array $ctx): ?string
    {
        if (! ($ctx['has_cat'] ?? false)) {
            return null;
        }

        if (($ctx['subtopic_skill'] ?? 'neutral') !== 'strong') {
            return null;
        }

        $title = $ctx['subtopic_title'] ?? 'этой теме';

        return "Похоже, вы уже хорошо разбираетесь в теме «{$title}». "
            . 'Предлагаем сразу начать с практической задачи — теорию можно посмотреть в любой момент '
            . 'по кнопке «Вернуться к теории».';
    }

    private function persistTask(UserProgressSubtopic $progress, array $block): void
    {
        $state = $this->getTheoryState($progress);
        $state['task'] = [
            'title'               => $block['title'] ?? null,
            'action'              => $block['action'] ?? null,
            'function_signature'  => $block['function_signature'] ?? null,
            'description'         => $block['description'] ?? null,
            'starter_code'        => $block['starter_code'] ?? null,
            'constraints'         => $block['constraints'] ?? [],
            'example_input'       => $block['example_input'] ?? '',
            'example_output'      => $block['example_output'] ?? '',
            'test_cases'          => $block['test_cases'] ?? [],
            'exam_block'          => $block['exam_block'] ?? false,
            'exam_language'       => $block['exam_language'] ?? null,
            'file_label'          => $block['file_label'] ?? null,
            'commit_hint'         => $block['commit_hint'] ?? null,
        ];

        $description = trim((string) ($block['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($block['action'] ?? $block['title'] ?? ''));
        }

        $progress->update([
            'task_title'       => $block['title'] ?? null,
            'task_description' => $description !== '' ? $description : null,
            'generated_theory' => json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function formatTaskBlock(
        array $task,
        string $direction,
        string $fallbackTitle,
        ?string $introMessage = null,
    ): array {
        $normalized = $this->normalizeTaskPayload($task, $direction, $fallbackTitle);

        $block = [
            'type'                 => 'task',
            'title'                => $normalized['title'],
            'action'               => $normalized['action'],
            'function_signature'   => $normalized['function_signature'],
            'description'          => $normalized['description'],
            'starter_code'         => $normalized['starter_code'],
            'constraints'          => $normalized['constraints'],
            'example_input'        => $normalized['example_input'],
            'example_output'       => $normalized['example_output'],
            'test_cases'           => $normalized['test_cases'],
        ];

        if ($introMessage !== null) {
            $block['intro_message'] = $introMessage;
        }

        return $block;
    }

    /** @return array<string, mixed> */
    private function normalizeTaskPayload(array $task, string $direction, string $fallbackTitle): array
    {
        $testCases = [];
        foreach ($task['test_cases'] ?? [] as $i => $case) {
            if (! is_array($case)) {
                continue;
            }
            $input  = trim((string) ($case['input'] ?? ''));
            $output = trim((string) ($case['output'] ?? ''));
            if ($input === '' && $output === '') {
                continue;
            }
            $testCases[] = [
                'label'  => trim((string) ($case['label'] ?? ('Пример ' . ($i + 1)))),
                'input'  => $input,
                'output' => $output,
            ];
        }

        if ($testCases === []) {
            $in  = trim((string) ($task['example_input'] ?? ''));
            $out = trim((string) ($task['example_output'] ?? ''));
            if ($in !== '' || $out !== '') {
                $testCases[] = [
                    'label'  => 'Пример 1',
                    'input'  => $in,
                    'output' => $out,
                ];
            }
        }

        $constraints = array_values(array_filter(array_map(
            fn ($c) => trim((string) $c),
            $task['constraints'] ?? [],
        ), fn ($c) => $c !== ''));

        $action = trim((string) ($task['action'] ?? ''));
        if ($action === '') {
            $action = 'Реализуйте функцию по условию ниже';
        }

        $signature = trim((string) ($task['function_signature'] ?? ''));

        return [
            'title'              => trim((string) ($task['title'] ?? $fallbackTitle)) ?: $fallbackTitle,
            'action'             => $action,
            'function_signature' => $signature !== '' ? $signature : null,
            'description'        => trim((string) ($task['description'] ?? '')),
            'starter_code'       => trim((string) ($task['starter_code'] ?? '')) ?: $this->starterCodeFor($direction),
            'constraints'        => $constraints,
            'example_input'      => $testCases[0]['input'] ?? trim((string) ($task['example_input'] ?? '')),
            'example_output'     => $testCases[0]['output'] ?? trim((string) ($task['example_output'] ?? '')),
            'test_cases'         => $testCases,
        ];
    }

    private function reviewCodeWithAi(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        array $ctx,
        string $code,
        array $sonarResult,
    ): array {
        $issuesText = collect($sonarResult['issues'] ?? [])
            ->take(8)
            ->map(fn ($i) => ($i['severity'] ?? '') . ': ' . ($i['message'] ?? ''))
            ->implode("\n");

        if (!$this->ollama->isAvailable()) {
            return $this->reviewFromSonarOnly($sonarResult);
        }

        $prompt  = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personaPrompt($ctx)}
Оцени решение по «{$subtopic->title}» (курс «{$ctx['course_title']}»).

Код студента:
```
{$code}
```

Замечания SonarQube (если не HTML/CSS — ориентир, не приговор):
{$issuesText}

Верни JSON:
{
  "is_correct": true/false,
  "score": 0-100,
  "feedback": "краткий итог на русском",
  "strengths": ["..."],
  "improvements": ["..."]
}
PROMPT;

        try {
            return $this->ollama->generateJson($prompt);
        } catch (\Exception $e) {
            Log::error('AI code review failed: ' . $e->getMessage());

            return $this->reviewFromSonarOnly($sonarResult);
        }
    }

    /** Оценка практики только по SonarQube, когда Ollama недоступен или вернул ошибку. */
    private function reviewFromSonarOnly(array $sonarResult): array
    {
        $issues = $sonarResult['issues'] ?? [];

        if (!($sonarResult['success'] ?? true) || !empty($sonarResult['error'])) {
            $message = (string) ($sonarResult['error'] ?? 'SonarQube не вернул результаты анализа');

            return [
                'is_correct'   => false,
                'score'        => 0,
                'feedback'     => 'ИИ недоступен, SonarQube не смог проанализировать код: ' . $message,
                'strengths'    => [],
                'improvements' => ['Проверьте SONAR_TOKEN и доступность SonarQube.'],
            ];
        }

        $blocking = $this->sonar->hasBlockingIssues($issues);
        $score    = $blocking ? 45 : (empty($issues) ? 85 : 65);
        $messages = array_values(array_filter(array_column(array_slice($issues, 0, 5), 'message')));

        return [
            'is_correct'   => !$blocking && $score >= 60,
            'score'        => $score,
            'feedback'     => $blocking
                ? 'ИИ недоступен. SonarQube нашёл критические замечания — исправьте их.'
                : (empty($issues)
                    ? 'ИИ недоступен. По SonarQube критичных замечаний нет — проверьте логику и синтаксис вручную.'
                    : 'ИИ недоступен. Оценка по замечаниям SonarQube (' . count($issues) . ').'),
            'strengths'    => empty($issues) ? ['Критичных замечаний SonarQube не обнаружено'] : [],
            'improvements' => $messages,
        ];
    }

    private function fallbackHint(string $hintType, string $taskDesc): array
    {
        $content = match ($hintType) {
            'plantuml', 'code_snippet' => "// Подсказка: начните с ввода/вывода и базовой структуры\n// Задача: {$taskDesc}",
            'simpler_solution' => "Упрощённый план:\n1. Определите входные данные\n2. Реализуйте базовый случай\n3. Добавьте проверки",
            'skeleton_hint' => "// Каркас — заполните TODO сами:\n// function solve(...) {\n//   // TODO: шаг 1\n//   // TODO: шаг 2\n// }",
            default => "Разбейте задачу на маленькие шаги.",
        };

        return [
            'type'      => 'hint',
            'hint_type' => $hintType,
            'title'     => 'Подсказка',
            'content'   => $content,
        ];
    }

    private function resolveProgress(
        int $userId,
        int $courseId,
        int $subtopicId,
        ?UserLearningProfile $profile,
    ): UserProgressSubtopic {
        $delivery = $profile
            ? $this->profileService->deliveryModeForPreference($profile->learning_preference)
            : 'full';

        return UserProgressSubtopic::firstOrCreate(
            [
                'user_id'     => $userId,
                'course_id'   => $courseId,
                'subtopic_id' => $subtopicId,
            ],
            ['delivery_mode' => $delivery]
        );
    }

    private function findNextSubtopic(Course $course, int $currentSubtopicId): ?array
    {
        $ordered = [];
        foreach ($course->modules as $module) {
            foreach ($module->themes as $theme) {
                foreach ($theme->subtopics->sortBy('order') as $sub) {
                    $ordered[] = $sub;
                }
            }
        }

        $found = false;
        foreach ($ordered as $sub) {
            if ($found) {
                return ['id' => $sub->id, 'title' => $sub->title];
            }
            if ($sub->id === $currentSubtopicId) {
                $found = true;
            }
        }

        return null;
    }

    private function updateCourseProgress(int $userId, Course $course): void
    {
        $total = $course->modules->flatMap(fn ($m) => $m->themes->flatMap(fn ($t) => $t->subtopics))->count();
        if ($total === 0) {
            return;
        }

        $completed = UserProgressSubtopic::forUserAndCourse($userId, $course->id)
            ->completed()
            ->count();

        $percent = (int) round(($completed / $total) * 100);

        $course->users()->updateExistingPivot($userId, [
            'progress' => min(100, $percent),
            'status'   => $percent >= 100 ? 'completed' : 'active',
        ]);
    }

    private function assertCourseAccess(int $userId, int $courseId): void
    {
        $exists = Course::where('id', $courseId)
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId))
            ->exists();

        if (!$exists) {
            throw new \RuntimeException('Нет доступа к курсу', 403);
        }
    }

    private function isVagueTaskDescription(string $description): bool
    {
        $lower = mb_strtolower(trim($description));
        if (mb_strlen($lower) < 100) {
            return true;
        }

        $vaguePhrases = [
            'проанализируй',
            'сложн',
            'структур',
            'оптимизируй',
            'улучши код',
            'реализуйте решение',
        ];

        foreach ($vaguePhrases as $phrase) {
            if (str_contains($lower, $phrase) && !str_contains($lower, 'например') && !str_contains($lower, 'пример')) {
                return true;
            }
        }

        return false;
    }

    private function isSubtopicContentReady(UserProgressSubtopic $progress): bool
    {
        $state = $this->getTheoryState($progress);

        $hasSlides = !empty($state['slides']) && !empty($state['pregenerated']);
        $hasTask   = $this->isStructuredTaskCached($progress);

        return $hasSlides && $hasTask;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateStructuredTaskBlock(
        string $basePrompt,
        Subtopic $subtopic,
        array $ctx,
        string $fallbackTitle,
        ?string $introMessage = null,
    ): array {
        $direction = $ctx['direction'];
        $lastBlock = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $prompt = $attempt === 0
                ? $basePrompt
                : $basePrompt . "\n\nПОВТОР: JSON не прошёл валидацию (структура, тон или запрещённые слова). Без легенд и без HP/лайков/соцсетей.\n"
                  . $this->contextBuilder->taskValidationReminder($ctx);

            try {
                $data  = $this->ollama->generateJson($prompt);
                $task  = $data['task'] ?? $data;
                $block = $this->formatTaskBlock($task, $direction, $fallbackTitle, $introMessage);
                $lastBlock = $block;

                if ($this->isStructuredTaskBlock($block)) {
                    return $block;
                }
            } catch (\Exception $e) {
                if ($attempt === 1) {
                    throw $e;
                }
            }
        }

        if ($lastBlock !== null) {
            Log::warning('Task JSON failed structure validation', [
                'subtopic_id' => $subtopic->id,
                'title'       => $lastBlock['title'] ?? null,
            ]);
        }

        throw new \RuntimeException('AI вернул задачу без обязательной структуры');
    }

    private function isStructuredTaskBlock(array $block): bool
    {
        if (! empty($block['exam_block'])) {
            $constraints = array_values(array_filter(
                $block['constraints'] ?? [],
                fn ($c) => trim((string) $c) !== '',
            ));

            return count($constraints) >= 2
                && trim((string) ($block['starter_code'] ?? '')) !== '';
        }

        $signature = trim((string) ($block['function_signature'] ?? ''));
        if ($signature === '') {
            return false;
        }

        $constraints = array_values(array_filter(
            $block['constraints'] ?? [],
            fn ($c) => trim((string) $c) !== '',
        ));
        if (count($constraints) < 2) {
            return false;
        }

        $cases = array_filter($block['test_cases'] ?? [], function ($case) {
            if (! is_array($case)) {
                return false;
            }
            $input  = trim((string) ($case['input'] ?? ''));
            $output = trim((string) ($case['output'] ?? ''));
            if ($input === '' || $output === '') {
                return false;
            }

            return ! str_contains($input, '…') && ! str_contains($output, '…');
        });

        return count($cases) >= 2;
    }

    private function violatesTaskTone(array $block, array $ctx): bool
    {
        $text = mb_strtolower(implode(' ', array_filter([
            (string) ($block['title'] ?? ''),
            (string) ($block['description'] ?? ''),
            (string) ($block['action'] ?? ''),
        ])));

        foreach ($this->contextBuilder->taskForbiddenPhrases($ctx) as $phrase) {
            if ($this->textContainsForbiddenPhrase($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function textContainsForbiddenPhrase(string $text, string $phrase): bool
    {
        $phrase = mb_strtolower(trim($phrase));
        if ($phrase === '') {
            return false;
        }

        if (mb_strlen($phrase) <= 3) {
            return (bool) preg_match(
                '/(?<![a-zа-яё0-9])' . preg_quote($phrase, '/') . '(?![a-zа-яё0-9])/ui',
                $text,
            );
        }

        return str_contains($text, $phrase);
    }

    private function isStructuredTaskCached(UserProgressSubtopic $progress, array $ctx = []): bool
    {
        if (! $progress->task_title) {
            return false;
        }

        $state = $this->getTheoryState($progress);
        $saved = is_array($state['task'] ?? null) ? $state['task'] : [];

        $block = [
            'title'              => $progress->task_title,
            'description'        => $progress->task_description,
            'action'             => $saved['action'] ?? '',
            'function_signature' => $saved['function_signature'] ?? null,
            'constraints'        => $saved['constraints'] ?? [],
            'test_cases'         => $saved['test_cases'] ?? [],
            'exam_block'         => $saved['exam_block'] ?? false,
            'starter_code'       => $saved['starter_code'] ?? '',
        ];

        if (! $this->isStructuredTaskBlock($block)) {
            return false;
        }

        if (! empty($block['exam_block'])) {
            return true;
        }

        $desc = trim((string) $progress->task_description);
        if ($desc !== '' && $this->isVagueTaskDescription($desc) && empty($saved['function_signature'])) {
            return false;
        }

        return $ctx === [] || ! $this->violatesTaskTone($block, $ctx);
    }

    private function pregenerateSubtopicContent(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
    ): void {
        if ($this->isSubtopicContentReady($progress)) {
            return;
        }

        $subtopic->loadMissing('theme.module.course');
        $course = $subtopic->theme->module->course;
        $ctx    = $this->contextBuilder->forCourse($progress->user_id, $course, $subtopic->title);

        $state     = $this->getTheoryState($progress);
        $hasSlides = ! empty($state['slides']) && ! empty($state['pregenerated']);

        if (! $hasSlides) {
            $totalParts = $ctx['theory_parts'] ?? 3;
            $slides     = $this->generateAllTheorySlides($subtopic, $profile, $ctx, $totalParts);
            $this->savePregeneratedTheory($progress, $slides, $totalParts, $ctx);
        }

        if (! $this->isStructuredTaskCached($progress->fresh(), $ctx)) {
            $this->buildTaskBlock($subtopic, $profile, $progress->fresh(), $ctx);
        }

        if (! $this->isSubtopicContentReady($progress->fresh())) {
            Log::warning('pregenerateSubtopicContent: content still not ready after build', [
                'subtopic_id' => $subtopic->id,
                'user_id'     => $progress->user_id,
            ]);
        }
    }

    private function generateAllTheorySlides(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        array $ctx,
        int $totalParts,
    ): array {
        $stored = $subtopic->theory;
        if ($stored && !$this->ollama->isAvailable()) {
            return [[
                'slide_title'   => $subtopic->title,
                'content'       => $stored,
                'callout'       => null,
                'diagram'       => null,
                'mentor_opener' => "Привет! Сегодня разберём «{$subtopic->title}».",
            ]];
        }

        if (!$this->ollama->isAvailable()) {
            $slides = [];
            for ($i = 1; $i <= $totalParts; $i++) {
                $slides[] = [
                    'slide_title' => "Шаг {$i}: {$subtopic->title}",
                    'content'     => "Шаг {$i} из {$totalParts} по теме «{$subtopic->title}».",
                    'callout'     => null,
                    'diagram'     => null,
                    'mentor_opener' => $i === 1 ? "Привет! Разберём «{$subtopic->title}»." : null,
                ];
            }

            return $slides;
        }

        $format = $this->contextBuilder->contentFormatRules();
        $schema = $this->contextBuilder->theoryBatchJsonSchema($totalParts, $ctx['direction']);
        $baseTheory = $stored ? "Опора из программы курса:\n{$stored}" : '';

        $sessionIntro = $this->contextBuilder->theorySessionIntro($ctx, $subtopic->title);

        $prompt = <<<PROMPT
{$this->contextBuilder->mentorStylePrompt($ctx)}
{$this->contextBuilder->personalizationPrompt($ctx)}
{$this->contextBuilder->domainPrompt($ctx)}
{$this->contextBuilder->theoryDepthRules($ctx)}
{$format}

{$sessionIntro}

Подготовь {$totalParts} слайдов по «{$subtopic->title}» для «{$ctx['course_title']}».
{$baseTheory}

Каждый слайд — конкретный навык. Примеры из домена студента. Последний слайд — мостик к практике.

{$schema}
PROMPT;

        try {
            $data   = $this->ollama->generateJson($prompt);
            $slides = $data['slides'] ?? [];

            if ($slides === []) {
                throw new \RuntimeException('Empty slides from AI');
            }

            return array_slice($slides, 0, $totalParts);
        } catch (\Exception $e) {
            Log::error('Batch theory generation failed: ' . $e->getMessage());

            return [[
                'slide_title'   => $subtopic->title,
                'content'       => $stored ?? "Теория по теме «{$subtopic->title}».",
                'callout'       => null,
                'diagram'       => null,
                'mentor_opener' => "Привет! Разберём «{$subtopic->title}».",
            ]];
        }
    }

    private function savePregeneratedTheory(
        UserProgressSubtopic $progress,
        array $slides,
        int $totalParts,
        array $ctx = [],
    ): void {
        $chunks = array_map(fn ($s) => $s['content'] ?? '', $slides);

        $progress->update([
            'generated_theory'  => json_encode([
                'pregenerated'            => true,
                'slides'                  => $slides,
                'chunks'                  => $chunks,
                'total_parts'             => $totalParts,
                'personalization_label'   => $ctx['personalization_label'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'theory_part_index' => 0,
            'theory_complete'   => false,
        ]);
    }

    private function markAsPregenerated(UserProgressSubtopic $progress): void
    {
        $state = $this->getTheoryState($progress);
        $state['pregenerated'] = true;
        if (!isset($state['slides']) && !empty($state['chunks'])) {
            $state['slides'] = array_map(fn ($c) => ['content' => $c], $state['chunks']);
        }
        $progress->update(['generated_theory' => json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }

    private function returnToTheory(Subtopic $subtopic, UserProgressSubtopic $progress): array
    {
        $state = $this->getTheoryState($progress);
        if (empty($state['slides'])) {
            if (!empty($state['chunks'])) {
                $state['slides'] = array_map(fn ($c) => ['content' => $c], $state['chunks']);
            } else {
                throw new \RuntimeException('Теория недоступна для этого урока.', 422);
            }
        }

        $progress->update([
            'theory_complete'   => false,
            'theory_part_index' => 0,
        ]);

        return $this->serveTheoryFromCache($subtopic, $progress->fresh(), $state);
    }

    private function retreatTheoryFromCache(Subtopic $subtopic, UserProgressSubtopic $progress): array
    {
        $state = $this->getTheoryState($progress);
        if (empty($state['slides'])) {
            if (!empty($state['chunks'])) {
                $state['slides'] = array_map(fn ($c) => ['content' => $c], $state['chunks']);
            } else {
                throw new \RuntimeException('Теория недоступна.', 422);
            }
        }

        $idx = (int) $progress->theory_part_index;
        if ($idx <= 0) {
            return $this->serveTheoryFromCache($subtopic, $progress, $state);
        }

        $progress->update([
            'theory_part_index' => $idx - 1,
            'theory_complete'   => false,
        ]);

        return $this->serveTheoryFromCache($subtopic, $progress->fresh(), $state);
    }

    private function serveTheoryFromCache(
        Subtopic $subtopic,
        UserProgressSubtopic $progress,
        array $state,
    ): array {
        $slides = $state['slides'];
        $total  = (int) ($state['total_parts'] ?? count($slides));
        $idx    = (int) $progress->theory_part_index;
        $label  = $state['personalization_label'] ?? null;

        if ($idx >= count($slides)) {
            return $this->formatTheorySlide(
                array_merge($slides[count($slides) - 1], ['has_more' => false]),
                count($slides),
                $total,
                $subtopic->title,
                $label,
            );
        }

        $slide     = $slides[$idx];
        $partIndex = $idx + 1;

        return $this->formatTheorySlide(
            array_merge($slide, ['has_more' => $partIndex < $total]),
            $partIndex,
            $total,
            $subtopic->title,
            $label,
        );
    }

    private function advanceTheoryFromCache(
        Subtopic $subtopic,
        ?UserLearningProfile $profile,
        UserProgressSubtopic $progress,
        array $ctx,
        array $state,
    ): array {
        $slides  = $state['slides'];
        $total   = (int) ($state['total_parts'] ?? count($slides));
        $nextIdx = (int) $progress->theory_part_index + 1;

        if ($nextIdx >= count($slides)) {
            $progress->update([
                'theory_complete'   => true,
                'theory_part_index' => $nextIdx,
            ]);

            return $this->buildTaskBlock($subtopic, $profile, $progress, $ctx);
        }

        $progress->update(['theory_part_index' => $nextIdx]);
        $slide     = $slides[$nextIdx];
        $partIndex = $nextIdx + 1;

        return $this->formatTheorySlide(
            array_merge($slide, ['has_more' => $partIndex < $total]),
            $partIndex,
            $total,
            $subtopic->title,
            $state['personalization_label'] ?? null,
        );
    }

    private function editorCodePromptSection(?string $code, string $direction): string
    {
        $trimmed = $code !== null ? trim($code) : '';
        if ($trimmed === '') {
            return 'Код в редакторе: пусто или студент ещё не написал решение.';
        }

        return "Текущий код студента в редакторе ({$direction}):\n```\n{$trimmed}\n```";
    }

    private function starterCodeFor(string $direction): string
    {
        return match (strtolower($direction)) {
            'php'        => "<?php\n\n// Ваше решение\n",
            'python'     => "# Ваше решение\n",
            'javascript', 'typescript' => "// Ваше решение\n",
            'java'       => "public class Solution {\n    // Ваше решение\n}\n",
            'c++'        => "#include <iostream>\n\nint main() {\n    // Ваше решение\n    return 0;\n}\n",
            default      => "// Ваше решение\n",
        };
    }
}
