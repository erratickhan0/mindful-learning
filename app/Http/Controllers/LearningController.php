<?php

namespace App\Http\Controllers;

use App\Services\LearningContentService;
use App\Services\LearningFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function __construct(
        protected LearningContentService $contentService,
        protected LearningFeedbackService $feedbackService
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'in:math,english,science'],
            'age_group' => ['required', 'in:4-6,7-9,10-12'],
            'type' => ['required', 'in:quiz,puzzle,activity'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);

        $content = $this->contentService->generate(
            $validated['subject'],
            $validated['age_group'],
            $validated['type'],
            $validated['difficulty'] ?? 'medium'
        );

        return response()->json([
            'data' => $content,
        ]);
    }

    public function attempt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'in:math,english,science'],
            'age_group' => ['required', 'in:4-6,7-9,10-12'],
            'type' => ['required', 'in:quiz,puzzle'],
            'prompt' => ['required', 'string', 'max:500'],
            'correct_answer' => ['required', 'string', 'max:100'],
            'submitted_answer' => ['required', 'string', 'max:100'],
        ]);

        $feedback = $this->feedbackService->evaluate($validated);

        return response()->json([
            'data' => $feedback,
        ]);
    }
}
