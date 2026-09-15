<?php

use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LoanProductController;
use App\Http\Controllers\Api\V1\TransactionController;
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

        // --- Loan products (catalog) ---
        Route::get('loan-products', [LoanProductController::class, 'index']);
        Route::get('loan-products/{loanProduct}', [LoanProductController::class, 'show']);
        Route::post('loan-products', [LoanProductController::class, 'store']);
        Route::patch('loan-products/{loanProduct}', [LoanProductController::class, 'update']);

        // --- Loan applications ---
        Route::get('loan-applications', [LoanApplicationController::class, 'index']);
        Route::post('loan-applications', [LoanApplicationController::class, 'store']);
        Route::get('loan-applications/{loanApplication}', [LoanApplicationController::class, 'show']);
        Route::post('loan-applications/{loanApplication}/submit', [LoanApplicationController::class, 'submit']);
        Route::post('loan-applications/{loanApplication}/cancel', [LoanApplicationController::class, 'cancel']);
        Route::post('loan-applications/{loanApplication}/assess', [LoanApplicationController::class, 'assess']);
        Route::post('loan-applications/{loanApplication}/approve', [LoanApplicationController::class, 'approve']);
        Route::post('loan-applications/{loanApplication}/reject', [LoanApplicationController::class, 'reject']);

        // --- Loans ---
        Route::get('loans', [LoanController::class, 'index']);
        Route::get('loans/{loan}', [LoanController::class, 'show']);
        Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])
            ->middleware('idempotency');

        // --- Transactions (read-only) ---
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

        // Remaining resource routes (repayments, audit-logs) are added in
        // their respective implementation phases.
        //
        // Example shape for Phase 8:
        //
        // Route::post('loans/{loan}/repayments', [RepaymentController::class, 'store'])
        //     ->middleware('idempotency');
    });
});
