<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnerInsightsController extends Controller
{
    public function progress(Request $request, User $learner): JsonResponse
    {
        if ($learner->role !== 'learner') {
            return response()->json(['message' => 'User is not a learner.'], 422);
        }

        if (! $this->canViewLearner($request, $learner)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $seed = $learner->id % 10;
        $completion = min(95, 45 + ($seed * 4));
        $accuracy = min(96, 50 + ($seed * 3));
        $weeklyMinutes = 40 + ($seed * 8);

        return response()->json([
            'progress' => [
                'completion_percent' => $completion,
                'accuracy_percent' => $accuracy,
                'weekly_minutes' => $weeklyMinutes,
                'focus_skill' => $this->focusSkill($seed),
            ],
        ]);
    }

    public function weeklyReport(Request $request, User $learner): JsonResponse
    {
        if ($learner->role !== 'learner') {
            return response()->json(['message' => 'User is not a learner.'], 422);
        }

        if (! $this->canViewLearner($request, $learner)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $seed = $learner->id % 10;

        return response()->json([
            'report' => [
                'summary' => 'Learner showed steady engagement this week with strong curiosity in interactive tasks.',
                'strengths' => [
                    'Consistent participation in short sessions',
                    'Improving confidence in guided quizzes',
                ],
                'focus_areas' => [
                    $this->focusSkill($seed),
                    'Reading comprehension speed',
                ],
                'next_week_plan' => [
                    'Monday: 10 min Math puzzle + 5 min review',
                    'Wednesday: 10 min English quiz + read aloud',
                    'Friday: 12 min Science activity + reflection',
                ],
            ],
        ]);
    }

    protected function canViewLearner(Request $request, User $learner): bool
    {
        $actor = $request->user();
        if (! $actor) {
            return false;
        }

        if ($actor->id === $learner->id || in_array($actor->role, ['admin', 'teacher'], true)) {
            return true;
        }

        return $actor->learnerLinks()->where('learner_user_id', $learner->id)->exists();
    }

    protected function focusSkill(int $seed): string
    {
        $skills = [
            'Math: multi-step addition',
            'English: sentence clarity',
            'Science: cause and effect',
            'Math: fractions comparison',
            'English: inference in short passages',
            'Science: states of matter',
        ];

        return $skills[$seed % count($skills)];
    }
}
