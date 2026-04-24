<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(protected ApiTokenService $apiTokenService)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:parent,learner,teacher,admin'],
            'age_group' => ['nullable', 'in:4-6,7-9,10-12'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'age_group' => $validated['age_group'] ?? null,
        ]);

        $tokenData = $this->apiTokenService->createForUser($user);

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $tokenData['plain_text_token'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $tokenData = $this->apiTokenService->createForUser($user);

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $tokenData['plain_text_token'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenId = $request->attributes->get('api_token_id');
        if ($tokenId) {
            ApiToken::whereKey($tokenId)->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    protected function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'age_group' => $user->age_group,
        ];
    }
}
