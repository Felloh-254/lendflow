<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repayments\StoreRepaymentRequest;
use App\Http\Resources\RepaymentResource;
use App\Http\Resources\RepaymentScheduleResource;
use App\Models\Loan;
use App\Services\IdempotencyService;
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
     * Requires an Idempotency-Key header (`idempotency` route middleware)
     * — unlike disbursement, this endpoint's request body (amount,
     * payment_method, external_reference) is meaningful to the
     * idempotency check: a retried request with the SAME key but a
     * DIFFERENT amount is rejected as a conflict rather than silently
     * replaying the wrong result. See docs/idempotency.md.
     */
    public function store(
        StoreRepaymentRequest $request,
        Loan $loan,
        RepaymentService $repayments,
        IdempotencyService $idempotency,
    ) {
        $this->authorize('repay', $loan);

        $validated = $request->validated();

        $result = $idempotency->handle(
            key: $request->header('Idempotency-Key'),
            userId: $request->user()->id,
            endpoint: "loans.{$loan->id}.repayments",
            payload: $validated,
            operation: function () use ($loan, $request, $repayments, $validated) {
                $repayment = $repayments->create(
                    loan: $loan,
                    actor: $request->user(),
                    amount: (float) $validated['amount'],
                    paymentMethod: $validated['payment_method'],
                    externalReference: $validated['external_reference'] ?? null,
                );

                return [
                    'status' => 201,
                    'body' => (new RepaymentResource($repayment))->resolve(),
                ];
            },
        );

        return response()->json($result['body'], $result['status'])
            ->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
    }
}
