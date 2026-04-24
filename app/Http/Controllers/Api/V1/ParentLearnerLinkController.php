<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParentLearnerLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentLearnerLinkController extends Controller
{
    public function index(Request $request, User $parent): JsonResponse
    {
        $actor = $request->user();
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Selected user is not a parent.'], 422);
        }

        if ($actor->id !== $parent->id && ! in_array($actor->role, ['admin', 'teacher'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $links = ParentLearnerLink::with('learner.learnerProfile')
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        $learners = $links->map(function (ParentLearnerLink $link) {
            return [
                'id' => $link->learner->id,
                'name' => $link->learner->name,
                'email' => $link->learner->email,
                'age_group' => $link->learner->age_group,
                'relationship' => $link->relationship,
                'profile' => [
                    'grade_level' => $link->learner->learnerProfile?->grade_level,
                    'reading_level' => $link->learner->learnerProfile?->reading_level,
                ],
            ];
        })->values();

        return response()->json([
            'learners' => $learners,
        ]);
    }

    public function store(Request $request, User $parent, User $learner): JsonResponse
    {
        $actor = $request->user();

        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Selected parent user is not a parent.'], 422);
        }

        if ($learner->role !== 'learner') {
            return response()->json(['message' => 'Selected learner user is not a learner.'], 422);
        }

        if ($actor->id !== $parent->id && ! in_array($actor->role, ['admin', 'teacher'], true)) {
            return response()->json(['message' => 'Only the parent account can create this link.'], 403);
        }

        $validated = $request->validate([
            'relationship' => ['nullable', 'in:mother,father,guardian,other'],
        ]);

        $link = ParentLearnerLink::firstOrCreate(
            [
                'parent_user_id' => $parent->id,
                'learner_user_id' => $learner->id,
            ],
            [
                'relationship' => $validated['relationship'] ?? 'guardian',
            ]
        );

        return response()->json([
            'link' => [
                'id' => $link->id,
                'parent_user_id' => $link->parent_user_id,
                'learner_user_id' => $link->learner_user_id,
                'relationship' => $link->relationship,
            ],
        ], 201);
    }
}
