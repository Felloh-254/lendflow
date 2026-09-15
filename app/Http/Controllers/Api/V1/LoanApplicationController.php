<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoanApplications\RejectLoanApplicationRequest;
use App\Http\Requests\LoanApplications\StoreLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\CreditAssessmentService;
use App\Services\LoanApplicationService;
use Illuminate\Http\Request;

class LoanApplicationController extends Controller
{
    public function __construct(private readonly LoanApplicationService $applications) {}

    /**
     * Row visibility is a query-scoping concern, not something the
     * `viewAny`/`view` Policy methods decide (see docs/authorization.md)
     * — a customer sees only their own applications, a loan officer sees
     * applications that are unassigned or assigned to them, and a manager
     * or admin sees everything.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', LoanApplication::class);

        $user = $request->user();

        $query = LoanApplication::query()->with(['loanProduct']);

        if ($user->isRole(User::ROLE_CUSTOMER)) {
            $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->isRole(User::ROLE_LOAN_OFFICER)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('assigned_loan_officer_id')
                    ->orWhere('assigned_loan_officer_id', $user->id);
            });
        }
        // Manager/Admin: no additional scoping — full portfolio visibility.

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        return LoanApplicationResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 20))
        );
    }

    public function show(LoanApplication $loanApplication)
    {
        $this->authorize('view', $loanApplication);

        return new LoanApplicationResource($loanApplication->load(['loanProduct', 'creditAssessment']));
    }

    public function store(StoreLoanApplicationRequest $request)
    {
        $customer = $request->user()->customer()->firstOrFail();
        $product = LoanProduct::findOrFail($request->loan_product_id);

        $application = $this->applications->create($customer, $product, $request->validated());

        return (new LoanApplicationResource($application))->response()->setStatusCode(201);
    }

    public function submit(Request $request, LoanApplication $loanApplication)
    {
        $this->authorize('submit', $loanApplication);

        $application = $this->applications->submit($loanApplication, $request->user());

        return new LoanApplicationResource($application);
    }

    public function cancel(Request $request, LoanApplication $loanApplication)
    {
        $this->authorize('cancel', $loanApplication);

        $application = $this->applications->cancel($loanApplication, $request->user());

        return new LoanApplicationResource($application);
    }

    public function assess(Request $request, LoanApplication $loanApplication, CreditAssessmentService $scoring)
    {
        $this->authorize('assess', $loanApplication);

        $application = $this->applications->assess($loanApplication, $request->user(), $scoring);

        return new LoanApplicationResource($application->load('creditAssessment'));
    }

    public function approve(Request $request, LoanApplication $loanApplication)
    {
        $this->authorize('decide', $loanApplication);

        $application = $this->applications->approve($loanApplication, $request->user());

        return new LoanApplicationResource($application);
    }

    public function reject(RejectLoanApplicationRequest $request, LoanApplication $loanApplication)
    {
        $this->authorize('decide', $loanApplication);

        $application = $this->applications->reject($loanApplication, $request->user(), $request->input('reason'));

        return new LoanApplicationResource($application);
    }
}
