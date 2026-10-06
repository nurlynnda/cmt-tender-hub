<?php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory()->status(TenderStatus::Awarded),
            'approved_margin_bp' => 1500,
            'project_charge_bp' => 900,
            'commission_share_bp' => 5000,
            'version' => 1,
        ];
    }
}
