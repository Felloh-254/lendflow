<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Models\User;
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
     * Requires an Idempotency-Key header. The full dedup/replay/conflict
     * decision happens in the `idempotency` route middleware BEFORE this
     * method (and this request's implicit route-model-binding, and any
     * validation on a future request body) ever runs — see
     * EnsureIdempotencyKey and docs/idempotency.md. This method only
     * executes at all on a genuinely new attempt.
     */
    public function disburse(Request $request, Loan $loan, LoanDisbursementService $disbursement)
    {
        $this->authorize('disburse', $loan);

        $updated = $disbursement->disburse($loan, $request->user());

        return new LoanResource($updated->load('loanProduct'));
    }
}
