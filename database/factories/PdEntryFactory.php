<?php

namespace Database\Factories;

use App\Enums\PdEntryType;
use App\Models\PdLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PdEntry> */
class PdEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pd_line_id' => PdLine::factory(),
            'type' => PdEntryType::Invoice,
            'number' => 'INV-'.fake()->numberBetween(1, 999),
            'date' => '2026-01-15',
            'amount_sen' => 100000,
        ];
    }
}
