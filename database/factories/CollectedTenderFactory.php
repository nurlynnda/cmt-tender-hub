<?php

namespace Database\Factories;

use App\Models\CollectedTender;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectedTenderFactory extends Factory
{
    public function definition(): array
    {
        $ref = fake()->unique()->numerify('QT2600000000#####');

        return [
            'dedup_key' => $ref,
            'reference_no' => $ref,
            'title' => strtoupper(fake()->sentence(8)),
            'status' => 'open',
            'procurement_type' => 'quotation',
            'ministry' => 'KEMENTERIAN KESIHATAN',
            'agency' => 'HOSPITAL KUALA LUMPUR',
            'category' => 'Bekalan',
            'advertised_date' => MalaysiaTime::today()->subDays(3)->toDateString(),
            'closing_date' => MalaysiaTime::today()->addDays(10)->toDateString(),
            'indicative_price_sen' => 2880000,
            'events' => [], 'winners' => null, 'raw' => [], 'field_updated_at' => [],
            'scraped_at' => now(),
        ];
    }

    public function forSource(string $source, ?string $sourceId = null): static
    {
        return $this->afterCreating(fn (CollectedTender $t) => $t->sources()->create([
            'source' => $source,
            'source_id' => $sourceId ?? (string) $t->id,
            'source_url' => "https://{$source}.example.test/{$t->id}",
        ]));
    }
}
