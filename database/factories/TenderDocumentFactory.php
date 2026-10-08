<?php

namespace Database\Factories;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenderDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'name' => fake()->words(3, true),
            'position' => 1,
            'is_done' => false,
        ];
    }
}
