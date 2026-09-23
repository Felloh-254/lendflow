<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repayments\StoreRepaymentRequest;
use App\Http\Resources\RepaymentResource;
use App\Http\Resources\RepaymentScheduleResource;
use App\Models\Loan;
use App\Services\RepaymentService;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    public function schedule(Request $request, Loan $loan)
    {
        $this->authorize('view', $loan);

        return RepaymentScheduleResource::collection($loan->repaymentSchedules);
    }

    public function index(Request $request, Loan $loan)
    {
        $this->authorize('view', $loan);

        return RepaymentResource::collection(
            $loan->repayments()->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 20))
        );
    }

    /**
     * Requires an Idempotency-Key header. As with disbursement, the full
     * dedup/replay/conflict decision happens in the `idempotency` route
     * middleware BEFORE StoreRepaymentRequest validates anything — which
     * matters specifically for `external_reference`'s `unique` rule: a
     * genuine retry with the same key and the same external_reference
     * must replay the original response, not fail validation because
     * the reference "is already taken" by the repayment IT created. See
     * EnsureIdempotencyKey and docs/idempotency.md.
     */
    public function store(StoreRepaymentRequest $request, Loan $loan, RepaymentService $repayments)
    {
        $this->authorize('repay', $loan);

        $validated = $request->validated();

        $repayment = $repayments->create(
            loan: $loan,
            actor: $request->user(),
            amount: (float) $validated['amount'],
            paymentMethod: $validated['payment_method'],
            externalReference: $validated['external_reference'] ?? null,
        );

        return (new RepaymentResource($repayment))->response()->setStatusCode(201);
    }
}
