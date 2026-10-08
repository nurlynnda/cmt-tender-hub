<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Quotation> */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'QTN-2026-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => QuotationStatus::Draft,
            'quote_date' => '2026-09-18',
            'validity_days' => 30,
            'customer_name' => fake()->company(),
            'subject' => fake()->sentence(5),
            'prepared_by' => User::factory(),
            'sst_bp' => 800,
            'terms' => "Prices quoted are in Ringgit Malaysia (RM).\nPayment terms: 30 days.",
            'letterhead' => [
                'name' => 'CMT Sdn. Bhd.', 'registration_no' => null, 'sst_no' => null, 'address' => 'Kuala Lumpur',
                'phone' => null, 'email' => null, 'website' => null, 'stamp_path' => null,
            ],
            'version' => 1,
        ];
    }
}
