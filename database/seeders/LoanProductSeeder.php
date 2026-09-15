<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Personal Loan',
                'description' => 'General-purpose loan for individual borrowers.',
                'min_amount' => 5000,
                'max_amount' => 300000,
                'interest_rate' => 14.5,
                'term_min' => 1,
                'term_max' => 24,
            ],
            [
                'name' => 'Business Loan',
                'description' => 'Working capital or expansion financing for small businesses.',
                'min_amount' => 50000,
                'max_amount' => 2000000,
                'interest_rate' => 16.0,
                'term_min' => 6,
                'term_max' => 36,
            ],
            [
                'name' => 'Emergency Loan',
                'description' => 'Fast, short-term financing for urgent needs.',
                'min_amount' => 1000,
                'max_amount' => 50000,
                'interest_rate' => 18.0,
                'term_min' => 1,
                'term_max' => 6,
            ],
        ];

        foreach ($products as $product) {
            LoanProduct::updateOrCreate(['name' => $product['name']], $product);
        }
    }
}
