<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Resolves the bearer token on every protected request, authenticates the
 * corresponding user, and returns a consistent 401 envelope on any failure
 * rather than letting Laravel's default (HTML/generic) error surface.
 */
class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            return $this->unauthorized('token_expired', 'Your session has expired. Please refresh your token.');
        } catch (TokenInvalidException) {
            return $this->unauthorized('token_invalid', 'The provided token is invalid.');
        } catch (\Tymon\JWTAuth\Exceptions\JWTException) {
            return $this->unauthorized('token_missing', 'No authentication token was provided.');
        }

        if (! $user || $user->status !== \App\Models\User::STATUS_ACTIVE) {
            return $this->unauthorized('account_inactive', 'This account is not active.');
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function unauthorized(string $code, string $message): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
