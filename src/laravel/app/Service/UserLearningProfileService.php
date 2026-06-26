<?php

namespace App\Service;

use App\Models\UserLearningProfile;
use Illuminate\Support\Facades\Log;

class UserLearningProfileService
{
    public const PREFERENCE_PRACTICE = 'practice_heavy';
    public const PREFERENCE_BALANCED = 'balanced';
    public const PREFERENCE_THEORY   = 'theory_heavy';

    public const PERSONA_STRICT    = 'strict_lead';
    public const PERSONA_COLLEAGUE = 'colleague';
    public const PERSONA_SOFT      = 'soft_mentor';

    public const GOAL_SENIOR    = 'senior_interview';
    public const GOAL_MID_LEVEL = 'mid_level_confidence';
    public const GOAL_STARTUP   = 'startup_architecture';

    public function saveFromOnboarding(int $userId, int $courseId, array $preferences): UserLearningProfile
    {
        $this->validatePreferences($preferences);

        $profile = UserLearningProfile::updateOrCreate(
            [
                'user_id'   => $userId,
                'course_id' => $courseId,
            ],
            [
                'learning_preference' => $preferences['style'],
                'domain_interest'     => $preferences['domain'] ?? null,
                'mentor_persona'      => $preferences['persona'],
                'career_goal'         => $preferences['goal'] ?? null,
                'struggle_score'      => 0,
                'success_streak'      => 0,
            ]
        );

        Log::info('UserLearningProfile saved', [
            'user_id'   => $userId,
            'course_id' => $courseId,
        ]);

        return $profile;
    }

    public function updatePreferences(int $userId, int $courseId, array $preferences): UserLearningProfile
    {
        $this->validatePreferences($preferences);

        $profile = $this->getProfile($userId, $courseId);
        if (!$profile) {
            throw new \RuntimeException('Профиль обучения не найден');
        }

        $profile->update([
            'learning_preference' => $preferences['style'] ?? $profile->learning_preference,
            'domain_interest'     => $preferences['domain'] ?? $profile->domain_interest,
            'mentor_persona'      => $preferences['persona'] ?? $profile->mentor_persona,
            'career_goal'         => $preferences['goal'] ?? $profile->career_goal,
        ]);

        return $profile->fresh();
    }

    public function getProfile(int $userId, int $courseId): ?UserLearningProfile
    {
        return UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    }

    public function hasProfile(int $userId, int $courseId): bool
    {
        return UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->exists();
    }

    public function deliveryModeForPreference(string $preference): string
    {
        return 'full';
    }

    private function validatePreferences(array $prefs): void
    {
        $validStyles   = [self::PREFERENCE_PRACTICE, self::PREFERENCE_BALANCED, self::PREFERENCE_THEORY];
        $validPersonas = [self::PERSONA_STRICT, self::PERSONA_COLLEAGUE, self::PERSONA_SOFT];
        $validGoals    = [self::GOAL_SENIOR, self::GOAL_MID_LEVEL, self::GOAL_STARTUP, null];

        if (!in_array($prefs['style'] ?? null, $validStyles, true)) {
            throw new \InvalidArgumentException('Invalid learning_preference');
        }

        if (!in_array($prefs['persona'] ?? null, $validPersonas, true)) {
            throw new \InvalidArgumentException('Invalid mentor_persona');
        }

        if (!in_array($prefs['goal'] ?? null, $validGoals, true)) {
            throw new \InvalidArgumentException('Invalid career_goal');
        }
    }
}
