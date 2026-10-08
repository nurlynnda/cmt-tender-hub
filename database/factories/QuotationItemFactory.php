<?php

namespace Database\Factories;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\QuotationItem> */
class QuotationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'position' => 1,
            'title' => fake()->words(3, true),
            'quantity' => 1,
            'unit' => 'Unit',
            'unit_price_sen' => 100000,
            'frequency' => 1,
            'unit_cost_sen' => 0,
            'margin_bp' => 2000,
            'unit_price_override_sen' => fn (array $a) => $a['unit_price_sen'], // like a migrated item: the price as set
            'has_sst' => true,
        ];
    }
}
