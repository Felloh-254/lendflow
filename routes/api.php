<?php

use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Public auth endpoints ---
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // --- Authenticated endpoints ---
    Route::middleware('jwt.auth')->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // --- Customer self-service ---
        // Authorization is enforced inside the controller via Policies
        // (CustomerPolicy), not by a role check here — see
        // docs/authorization.md for why that split is deliberate.
        Route::prefix('customers')->group(function () {
            Route::get('me', [CustomerController::class, 'me']);
            Route::patch('me', [CustomerController::class, 'updateMe']);
        });

        // --- Admin: user management ---
        Route::prefix('admin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);
        });

        // Remaining resource routes (loan-products, loan-applications,
        // loans, repayments, transactions, audit-logs) are added in their
        // respective implementation phases, each behind the relevant Policy.
        //
        // Example shape for Phase 6 onward:
        //
        // Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])
        //     ->middleware('idempotency');
        //
        // Route::post('loans/{loan}/repayments', [RepaymentController::class, 'store'])
        //     ->middleware('idempotency');
    });
});
