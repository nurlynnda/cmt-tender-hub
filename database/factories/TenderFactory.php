<?php

namespace Database\Factories;

use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Models\User;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wo_number' => fake()->unique()->numerify('200-01012026-###'),
            'wo_date' => '2026-01-01',
            'mode' => TenderMode::Ep,
            'type' => TenderType::Tender,
            'category' => TenderCategory::ItInfrastructure,
            'tender_code' => fake()->numerify('QT2600000000#####'),
            'title' => strtoupper(fake()->sentence(8)),
            'client' => 'KEMENTERIAN KESIHATAN',
            'pic_id' => User::factory(),
            'owner_id' => null,
            'closing_date' => MalaysiaTime::today()->addDays(30)->toDateString(),
            'has_briefing' => false,
            'estimated_value_sen' => 50000000,
            'status' => TenderStatus::InProgress,
            'version' => 1,
        ];
    }

    public function status(TenderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
