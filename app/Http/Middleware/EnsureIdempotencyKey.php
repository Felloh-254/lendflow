<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an `Idempotency-Key` header on financial write endpoints.
 *
 * The actual "have we seen this key before" lookup/short-circuit lives in
 * App\Services\IdempotencyService, wired up when the disbursement and
 * repayment endpoints are implemented (Phases 6–7). This middleware's job
 * is narrow and stable: reject requests that don't even carry a key.
 */
class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('Idempotency-Key')) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_missing',
                    'message' => 'This endpoint requires an Idempotency-Key header.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
