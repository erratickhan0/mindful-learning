<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnerController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'age_group' => ['required', 'in:4-6,7-9,10-12'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'reading_level' => ['nullable', 'in:early,basic,fluent'],
            'pace_level' => ['nullable', 'in:slow,steady,fast'],
            'confidence_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'attention_window_minutes' => ['nullable', 'integer', 'min:5', 'max:60'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
        ]);

        $actor = $request->user();
        if (! in_array($actor->role, ['parent', 'admin', 'teacher'], true)) {
            return response()->json(['message' => 'Only parent/teacher/admin can create learner.'], 403);
        }

        $learner = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'learner',
            'age_group' => $validated['age_group'],
        ]);

        $profile = LearnerProfile::create([
            'user_id' => $learner->id,
            'grade_level' => $validated['grade_level'] ?? null,
            'reading_level' => $validated['reading_level'] ?? 'basic',
            'pace_level' => $validated['pace_level'] ?? 'steady',
            'confidence_level' => $validated['confidence_level'] ?? 50,
            'attention_window_minutes' => $validated['attention_window_minutes'] ?? 10,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
        ]);

        return response()->json([
            'learner' => $this->serializeLearner($learner, $profile),
        ], 201);
    }

    public function show(Request $request, User $learner): JsonResponse
    {
        if ($learner->role !== 'learner') {
            return response()->json(['message' => 'User is not a learner.'], 422);
        }

        $actor = $request->user();
        $isOwner = $actor->id === $learner->id;
        $isParentLinked = $actor->learnerLinks()->where('learner_user_id', $learner->id)->exists();
        if (! $isOwner && ! $isParentLinked && ! in_array($actor->role, ['admin', 'teacher'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'learner' => $this->serializeLearner($learner, $learner->learnerProfile),
        ]);
    }

    public function update(Request $request, User $learner): JsonResponse
    {
        if ($learner->role !== 'learner') {
            return response()->json(['message' => 'User is not a learner.'], 422);
        }

        $actor = $request->user();
        $isParentLinked = $actor->learnerLinks()->where('learner_user_id', $learner->id)->exists();
        if (! $isParentLinked && ! in_array($actor->role, ['admin', 'teacher'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'age_group' => ['sometimes', 'in:4-6,7-9,10-12'],
            'grade_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'reading_level' => ['sometimes', 'in:early,basic,fluent'],
            'pace_level' => ['sometimes', 'in:slow,steady,fast'],
            'confidence_level' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'attention_window_minutes' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'preferred_language' => ['sometimes', 'string', 'max:10'],
        ]);

        if (array_key_exists('name', $validated)) {
            $learner->name = $validated['name'];
        }
        if (array_key_exists('age_group', $validated)) {
            $learner->age_group = $validated['age_group'];
        }
        $learner->save();

        $profile = $learner->learnerProfile()->firstOrCreate(['user_id' => $learner->id]);
        $profile->fill(collect($validated)->except(['name', 'age_group'])->toArray());
        $profile->save();

        return response()->json([
            'learner' => $this->serializeLearner($learner, $profile),
        ]);
    }

    protected function serializeLearner(User $learner, ?LearnerProfile $profile): array
    {
        return [
            'id' => $learner->id,
            'name' => $learner->name,
            'email' => $learner->email,
            'age_group' => $learner->age_group,
            'profile' => [
                'grade_level' => $profile?->grade_level,
                'reading_level' => $profile?->reading_level,
                'pace_level' => $profile?->pace_level,
                'confidence_level' => $profile?->confidence_level,
                'attention_window_minutes' => $profile?->attention_window_minutes,
                'preferred_language' => $profile?->preferred_language,
            ],
        ];
    }
}
