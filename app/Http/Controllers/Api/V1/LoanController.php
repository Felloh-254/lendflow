<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\LoanDisbursementService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Loan::class);

        $user = $request->user();
        $query = Loan::query()->with('loanProduct');

        if ($user->isRole(User::ROLE_CUSTOMER)) {
            $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id));
        }
        // Loan Officer/Manager/Admin: full portfolio visibility (view-loan-portfolio Gate governs the index itself).

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        return LoanResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);

        return new LoanResource($loan->load('loanProduct'));
    }

    /**
     * Requires an Idempotency-Key header (enforced by the `idempotency`
     * route middleware). The actual dedup/replay logic lives in
     * IdempotencyService — this controller's job is just to wire the
     * disbursement operation up as the callback it executes at most once.
     */
    public function disburse(
        Request $request,
        Loan $loan,
        LoanDisbursementService $disbursement,
        IdempotencyService $idempotency,
    ) {
        $this->authorize('disburse', $loan);

        $result = $idempotency->handle(
            key: $request->header('Idempotency-Key'),
            userId: $request->user()->id,
            endpoint: "loans.{$loan->id}.disburse",
            payload: [], // no meaningful request body for this endpoint — the key alone identifies the attempt
            operation: function () use ($loan, $request, $disbursement) {
                $updated = $disbursement->disburse($loan, $request->user());

                return [
                    'status' => 200,
                    'body' => (new LoanResource($updated->load('loanProduct')))->resolve(),
                ];
            },
        );

        return response()->json($result['body'], $result['status'])
            ->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
    }
}
