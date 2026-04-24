<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiLearningService
{
    public function generateContent(
        string $subject,
        string $ageGroup,
        string $type,
        string $difficulty
    ): ?array {
        $response = $this->chat([
            [
                'role' => 'system',
                'content' => 'You generate child-safe educational content. Return strict JSON only.',
            ],
            [
                'role' => 'user',
                'content' => "Generate one {$type} for subject={$subject}, age_group={$ageGroup}, difficulty={$difficulty}. ".
                    'Return JSON with keys: title, prompt, options (array, empty for activity), answer (empty for activity), hint (empty for activity). Keep language simple and fun.',
            ],
        ]);

        if (! $response) {
            return null;
        }

        return $this->extractJson($response);
    }

    public function generateFeedback(array $attempt): ?array
    {
        $response = $this->chat([
            [
                'role' => 'system',
                'content' => 'You are an encouraging AI tutor for children. Return strict JSON only.',
            ],
            [
                'role' => 'user',
                'content' => 'Given this attempt, provide child-friendly feedback in JSON keys: message, micro_hint, next_recommendation. '
                    .'Attempt: '.json_encode($attempt, JSON_UNESCAPED_UNICODE),
            ],
        ]);

        if (! $response) {
            return null;
        }

        return $this->extractJson($response);
    }

    protected function chat(array $messages): ?string
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model', 'gpt-4o-mini');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withToken($apiKey)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'temperature' => 0.7,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            return data_get($response->json(), 'choices.0.message.content');
        } catch (\Throwable $e) {
            Log::warning('OpenAI request exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    protected function extractJson(string $content): ?array
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```json\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
