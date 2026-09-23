<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owns the ENTIRE idempotency lifecycle for a request — not just the
 * "is the header present" check it used to be limited to. This matters
 * for a specific, real bug: a repayment carries a `unique` validation
 * rule on `external_reference` (mirroring the DB constraint). If the
 * idempotency check happened inside the controller — AFTER
 * StoreRepaymentRequest has already validated the request — a genuine
 * retry of an already-succeeded repayment would fail validation with
 * `422 "external reference already taken"` before ever reaching the
 * idempotency replay logic, defeating the whole guarantee.
 *
 * Middleware runs BEFORE the framework resolves and validates a
 * FormRequest (that resolution happens when the container builds the
 * controller method's arguments, which only occurs once `$next($request)`
 * is called here). So on a detected replay, this middleware returns the
 * cached response directly and never calls `$next($request)` at all —
 * validation, the Policy check, and the controller never run a second
 * time. See docs/idempotency.md.
 */
class EnsureIdempotencyKey
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_missing',
                    'message' => 'This endpoint requires an Idempotency-Key header.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // The RAW request body — deliberately not $request->validated(),
        // since validation hasn't happened yet at this point in the
        // pipeline. This is what makes the fix work: the hash used to
        // detect a duplicate is computed from exactly what the client
        // sent, independent of whether that request would pass or fail
        // validation.
        $payload = $request->all();

        // A stable, automatic identifier for "which endpoint" — the
        // resolved path (route parameters already substituted, e.g.
        // "api/v1/loans/5/repayments") plus the HTTP method. This
        // replaces the hand-built strings ("loans.{id}.repayments") the
        // controllers used to construct themselves; every idempotent
        // route gets a correct, unique scope for free.
        $endpoint = $request->method().' '.$request->path();

        $result = $this->idempotency->handle(
            key: $key,
            userId: $request->user()?->id,
            endpoint: $endpoint,
            payload: $payload,
            operation: function () use ($next, $request) {
                $response = $next($request);

                return [
                    'status' => $response->getStatusCode(),
                    'body' => json_decode($response->getContent(), true),
                ];
            },
        );

        return response()->json($result['body'], $result['status'])
            ->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
    }
}
