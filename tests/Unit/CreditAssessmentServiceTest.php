<?php

use App\Models\CreditAssessment;
use App\Services\CreditAssessmentService;

beforeEach(function () {
    $this->service = new CreditAssessmentService;
});

it('recommends approval for a low-DTI, well-covered request', function () {
    $result = $this->service->assess(monthlyIncome: 80000, existingDebt: 10000, amountRequested: 50000);

    expect($result['debt_to_income_ratio'])->toBe(0.125);
    expect($result['risk_level'])->toBe(CreditAssessment::RISK_LOW);
    expect($result['recommendation'])->toBe(CreditAssessment::RECOMMEND_APPROVE);
    expect($result['credit_score'])->toBeGreaterThanOrEqual(720);
});

it('recommends rejection for a high-DTI applicant', function () {
    $result = $this->service->assess(monthlyIncome: 50000, existingDebt: 40000, amountRequested: 20000);

    expect($result['debt_to_income_ratio'])->toBe(0.8);
    expect($result['risk_level'])->toBe(CreditAssessment::RISK_HIGH);
    expect($result['recommendation'])->toBe(CreditAssessment::RECOMMEND_REJECT);
});

it('penalizes a request that is large relative to income even with acceptable DTI', function () {
    $withoutLargeRequest = $this->service->assess(monthlyIncome: 60000, existingDebt: 5000, amountRequested: 50000);
    $withLargeRequest = $this->service->assess(monthlyIncome: 60000, existingDebt: 5000, amountRequested: 500000);

    expect($withLargeRequest['credit_score'])->toBeLessThan($withoutLargeRequest['credit_score']);
});

it('treats zero income as maximum risk rather than dividing by zero', function () {
    $result = $this->service->assess(monthlyIncome: 0, existingDebt: 0, amountRequested: 10000);

    expect($result['debt_to_income_ratio'])->toBe(1.0);
    expect($result['recommendation'])->toBe(CreditAssessment::RECOMMEND_REJECT);
});

it('always returns a score within the standard 300-850 range', function () {
    $result = $this->service->assess(monthlyIncome: 10000, existingDebt: 100000, amountRequested: 1000000);

    expect($result['credit_score'])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(850);
});
