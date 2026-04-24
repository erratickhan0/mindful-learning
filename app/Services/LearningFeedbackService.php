<?php

namespace App\Services;

class LearningFeedbackService
{
    public function __construct(protected OpenAiLearningService $openAiLearningService)
    {
    }

    public function evaluate(array $attempt): array
    {
        $correctAnswer = mb_strtolower(trim($attempt['correct_answer']));
        $submittedAnswer = mb_strtolower(trim($attempt['submitted_answer']));
        $isCorrect = $correctAnswer === $submittedAnswer;

        $feedback = $isCorrect
            ? $this->successFeedback($attempt['age_group'], $attempt['subject'])
            : $this->retryFeedback($attempt['age_group'], $attempt['subject']);

        $result = [
            'is_correct' => $isCorrect,
            'score_delta' => $isCorrect ? 10 : -2,
            'streak_delta' => $isCorrect ? 1 : -1,
            'message' => $feedback['message'],
            'micro_hint' => $isCorrect ? null : $feedback['hint'],
            'next_recommendation' => $feedback['next_recommendation'],
            'source' => 'local',
        ];

        $aiFeedback = $this->openAiLearningService->generateFeedback([
            'subject' => $attempt['subject'],
            'age_group' => $attempt['age_group'],
            'prompt' => $attempt['prompt'],
            'is_correct' => $isCorrect,
            'correct_answer' => $attempt['correct_answer'],
            'submitted_answer' => $attempt['submitted_answer'],
        ]);

        if ($this->isValidAiFeedback($aiFeedback)) {
            $result['message'] = $aiFeedback['message'];
            $result['micro_hint'] = $isCorrect ? null : $aiFeedback['micro_hint'];
            $result['next_recommendation'] = $aiFeedback['next_recommendation'];
            $result['source'] = 'openai';
        }

        return $result;
    }

    protected function successFeedback(string $ageGroup, string $subject): array
    {
        $subjectLabels = [
            'math' => 'Math',
            'english' => 'English',
            'science' => 'Science',
        ];

        return [
            'message' => match ($ageGroup) {
                '4-6' => 'Amazing! You solved it like a superstar!',
                '7-9' => 'Great work! You are leveling up fast.',
                default => 'Excellent accuracy. Keep this momentum going.',
            },
            'next_recommendation' => "Try a medium {$subjectLabels[$subject]} puzzle next for deeper practice.",
        ];
    }

    protected function retryFeedback(string $ageGroup, string $subject): array
    {
        $ageHints = [
            '4-6' => 'Take a deep breath and try one small step.',
            '7-9' => 'Break the question into two easier parts.',
            '10-12' => 'Use the concept rule first, then compute.',
        ];

        return [
            'message' => 'Good effort. Mistakes help your brain grow.',
            'hint' => $ageHints[$ageGroup],
            'next_recommendation' => "Retry a simpler {$subject} quiz, then attempt this level again.",
        ];
    }

    protected function isValidAiFeedback(?array $feedback): bool
    {
        if (! is_array($feedback)) {
            return false;
        }

        return isset($feedback['message'], $feedback['next_recommendation']);
    }
}
