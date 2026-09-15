<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoanProducts\StoreLoanProductRequest;
use App\Http\Requests\LoanProducts\UpdateLoanProductRequest;
use App\Http\Resources\LoanProductResource;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    /**
     * Any authenticated role can browse the active product catalog — a
     * customer needs it to apply, staff need it for context. Only admins
     * can create/update products, enforced via the `manage-loan-products`
     * Gate rather than a Policy, since there's no per-record ownership
     * question here — it's a flat "are you an admin" decision.
     */
    public function index(Request $request)
    {
        $products = LoanProduct::query()
            ->when(! $request->user()->isRole(\App\Models\User::ROLE_ADMIN), fn ($q) => $q->where('status', LoanProduct::STATUS_ACTIVE))
            ->orderBy('name')
            ->get();

        return LoanProductResource::collection($products);
    }

    public function show(LoanProduct $loanProduct)
    {
        return new LoanProductResource($loanProduct);
    }

    public function store(StoreLoanProductRequest $request)
    {
        $this->authorize('manage-loan-products');

        $product = LoanProduct::create($request->validated());

        return (new LoanProductResource($product))->response()->setStatusCode(201);
    }

    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct)
    {
        $this->authorize('manage-loan-products');

        $loanProduct->update($request->validated());

        return new LoanProductResource($loanProduct);
    }
}
