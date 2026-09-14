<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function me(Request $request)
    {
        $customer = $request->user()->customer()->firstOrFail();

        $this->authorize('view', $customer);

        return new CustomerResource($customer->load('user'));
    }

    public function updateMe(UpdateCustomerRequest $request)
    {
        $customer = $request->user()->customer()->firstOrFail();

        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return new CustomerResource($customer->load('user'));
    }
}
