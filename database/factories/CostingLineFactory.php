<?php

namespace Database\Factories;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CostingLine> */
class CostingLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'position' => 1,
            'description' => fake()->words(4, true),
            'unit' => 'unit',
            'quantity' => 1,
            'frequency' => 'one_off',
            'months' => 1,
            'project_year' => 1,
            'unit_cost_sen' => 10000000,
            'margin_bp' => 2000,
        ];
    }
}
