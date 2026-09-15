<?php

use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => JwtAuthenticate::class,
            'idempotency' => EnsureIdempotencyKey::class,
        ]);

        // Stateless API — no CSRF/session middleware needed on the api group.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON error envelopes for every exception on API routes,
        // instead of Laravel's default HTML error pages.
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\App\Exceptions\InvalidStateTransitionException $e, $request) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_state_transition',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (\App\Exceptions\InsufficientOutstandingBalanceException $e, $request) {
            return response()->json([
                'error' => [
                    'code' => 'insufficient_outstanding_balance',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (\App\Exceptions\IdempotencyConflictException $e, $request) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_conflict',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (\App\Exceptions\IdempotencyKeyInUseException $e, $request) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_in_use',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        });
    })->create();
