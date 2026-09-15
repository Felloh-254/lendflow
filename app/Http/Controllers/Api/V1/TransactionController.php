<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only by design — nothing in the API writes a Transaction directly.
 * Every row here is a side effect of LedgerService::post(), called from
 * LoanDisbursementService (and RepaymentService, from Phase 8 onward).
 */
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Transaction::query()->with('ledgerEntries');

        if ($user->isRole(User::ROLE_CUSTOMER)) {
            $query->whereHas('loan.customer', fn ($q) => $q->where('user_id', $user->id));
        }

        $query->when($request->filled('loan_id'), fn ($q) => $q->where('loan_id', $request->integer('loan_id')));
        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')));

        return TransactionResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Request $request, Transaction $transaction)
    {
        $user = $request->user();

        if ($user->isRole(User::ROLE_CUSTOMER) && $transaction->loan->customer->user_id !== $user->id) {
            abort(403);
        }

        return new TransactionResource($transaction->load('ledgerEntries'));
    }
}
