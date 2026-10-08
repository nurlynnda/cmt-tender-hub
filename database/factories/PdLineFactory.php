<?php

namespace Database\Factories;

use App\Enums\PdGroup;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PdLine> */
class PdLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'position' => 1,
            'pd_group' => PdGroup::Principal,
            'name' => fake()->words(3, true),
            'budget_sen' => 1000000,
            'version' => 1,
        ];
    }
}
