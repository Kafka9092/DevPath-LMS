<?php

namespace App\Service;

use App\Models\UserLearningProfile;
use Illuminate\Support\Facades\Log;

class UserBehaviorService
{
    public function __construct(
        protected MentorHelpService $mentorHelp,
    ) {}

    /** @var list<string> */
    private const STALL_EVENTS = [
        'stall_empty_screen',
        'stall_rewrite_cycle',
        'stall_idle_pause',
    ];

    /** @var list<string> */
    private const PASSIVE_EVENTS = [
        'tab_return',
        'undo_burst',
        'delete_spike',
        'rewrite_burst',
        'cursor_back',
        'code_edit',
        'code_run',
    ];

    public function recordEvent(
        int $userId,
        int $courseId,
        ?int $subtopicId,
        string $eventType,
        array $metadata = [],
    ): array {
        $profile = UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$profile) {
            if (in_array($eventType, self::STALL_EVENTS, true)) {
                return [
                    'recorded'     => true,
                    'mentor_block' => $this->mentorHelp->promptBlock(),
                    'stall_type'   => $eventType,
                ];
            }

            return ['recorded' => true, 'mentor_block' => null];
        }

        if (in_array($eventType, self::PASSIVE_EVENTS, true)) {
            return ['recorded' => true, 'mentor_block' => null];
        }

        if (!in_array($eventType, self::STALL_EVENTS, true)) {
            return ['recorded' => true, 'mentor_block' => null];
        }

        if (!$this->mentorHelp->shouldOfferStallHelp($profile)) {
            return ['recorded' => true, 'mentor_block' => null];
        }

        $this->mentorHelp->markHelpOffered($profile);

        Log::info('Mentor stall prompt triggered', [
            'user_id'    => $userId,
            'subtopic_id' => $subtopicId,
            'event_type' => $eventType,
        ]);

        return [
            'recorded'     => true,
            'mentor_block' => $this->mentorHelp->promptBlock(),
            'stall_type'   => $eventType,
        ];
    }

    public function recordSuccess(int $userId, int $courseId): void
    {
        $profile = UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$profile) {
            return;
        }

        $profile->increment('success_streak');
        $profile->update(['struggle_score' => max(0, $profile->struggle_score - 2)]);
    }

    public function recordFailure(int $userId, int $courseId): void
    {
        $profile = UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$profile) {
            return;
        }

        $profile->update(['success_streak' => 0]);
    }
}
