<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function __construct(protected ApiTokenService $apiTokenService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        $plainTextToken = str_starts_with($header, 'Bearer ')
            ? substr($header, 7)
            : null;

        if (! $plainTextToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = $this->apiTokenService->findValidToken($plainTextToken);
        if (! $token) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token_id', $token->id);

        return $next($request);
    }
}
