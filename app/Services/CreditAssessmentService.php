<?php

namespace App\Services;

use App\Models\CreditAssessment;

/**
 * A deterministic, rule-based credit scoring model.
 *
 * This is intentionally NOT a machine-learning model — the point of this
 * service is to demonstrate clean business-logic/service-layer design
 * with rules a reviewer can verify by hand, not to build a real scoring
 * engine. Every constant below is a business rule, not a magic number,
 * and each is named so the "why" is legible without a comment.
 *
 * Rules (see docs for the full writeup once it lands):
 *  1. Start from a base score.
 *  2. Reward low debt-to-income ratio, penalize high DTI.
 *  3. Penalize a requested amount that's large relative to monthly income.
 *  4. Clamp to the standard 300–850 credit score range.
 *  5. Score >= APPROVE_THRESHOLD and DTI <= MAX_APPROVABLE_DTI => approve.
 */
class CreditAssessmentService
{
    private const BASE_SCORE = 600;

    private const MIN_SCORE = 300;

    private const MAX_SCORE = 850;

    // Debt-to-income ratio breakpoints and their score adjustments.
    private const DTI_EXCELLENT = 0.20;  // <= 20% DTI

    private const DTI_ACCEPTABLE = 0.35; // <= 35% DTI

    private const DTI_RISKY = 0.50;      // <= 50% DTI (anything above is high risk)

    private const DTI_EXCELLENT_BONUS = 150;

    private const DTI_ACCEPTABLE_BONUS = 60;

    private const DTI_RISKY_PENALTY = -80;

    private const DTI_HIGH_PENALTY = -200;

    // How large the requested loan is relative to monthly income.
    private const REQUEST_TO_INCOME_HIGH = 6.0; // requested > 6x monthly income

    private const REQUEST_TO_INCOME_PENALTY = -60;

    // Thresholds that decide the final recommendation.
    private const APPROVE_SCORE_THRESHOLD = 650;

    private const APPROVE_MAX_DTI = self::DTI_ACCEPTABLE;

    public function assess(
        float $monthlyIncome,
        float $existingDebt,
        float $amountRequested,
    ): array {
        $dti = $monthlyIncome > 0 ? round($existingDebt / $monthlyIncome, 4) : 1.0;

        $score = self::BASE_SCORE + $this->dtiAdjustment($dti) + $this->requestSizeAdjustment($amountRequested, $monthlyIncome);
        $score = (int) max(self::MIN_SCORE, min(self::MAX_SCORE, $score));

        $riskLevel = $this->riskLevelFor($dti, $score);
        $recommendation = ($score >= self::APPROVE_SCORE_THRESHOLD && $dti <= self::APPROVE_MAX_DTI)
            ? CreditAssessment::RECOMMEND_APPROVE
            : CreditAssessment::RECOMMEND_REJECT;

        return [
            'credit_score' => $score,
            'monthly_income' => $monthlyIncome,
            'existing_debt' => $existingDebt,
            'debt_to_income_ratio' => $dti,
            'risk_level' => $riskLevel,
            'recommendation' => $recommendation,
        ];
    }

    private function dtiAdjustment(float $dti): int
    {
        return match (true) {
            $dti <= self::DTI_EXCELLENT => self::DTI_EXCELLENT_BONUS,
            $dti <= self::DTI_ACCEPTABLE => self::DTI_ACCEPTABLE_BONUS,
            $dti <= self::DTI_RISKY => self::DTI_RISKY_PENALTY,
            default => self::DTI_HIGH_PENALTY,
        };
    }

    private function requestSizeAdjustment(float $amountRequested, float $monthlyIncome): int
    {
        if ($monthlyIncome <= 0) {
            return self::REQUEST_TO_INCOME_PENALTY;
        }

        return ($amountRequested / $monthlyIncome) > self::REQUEST_TO_INCOME_HIGH
            ? self::REQUEST_TO_INCOME_PENALTY
            : 0;
    }

    private function riskLevelFor(float $dti, int $score): string
    {
        if ($score >= 720 && $dti <= self::DTI_EXCELLENT) {
            return CreditAssessment::RISK_LOW;
        }

        if ($score >= self::APPROVE_SCORE_THRESHOLD && $dti <= self::DTI_ACCEPTABLE) {
            return CreditAssessment::RISK_MEDIUM;
        }

        return CreditAssessment::RISK_HIGH;
    }
}
