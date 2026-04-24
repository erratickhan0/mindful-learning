<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function createForUser(User $user): array
    {
        $plainTextToken = Str::random(80);

        $token = $user->apiTokens()->create([
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'token_id' => $token->id,
        ];
    }

    public function findValidToken(string $plainTextToken): ?ApiToken
    {
        $tokenHash = hash('sha256', $plainTextToken);

        return ApiToken::query()
            ->where('token_hash', $tokenHash)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
