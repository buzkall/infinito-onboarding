<?php

namespace Arzcode\InfinitoOnboarding\Database\Factories;

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourCompletion>
 */
class TourCompletionFactory extends Factory
{
    protected $model = TourCompletion::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'user_id' => (string) fake()->numberBetween(1, 1000),
            'tenant_id' => TourCompletion::NO_TENANT,
            'seen_version' => '1',
            'completed_at' => now(),
            'dismissed_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn () => ['completed_at' => null, 'dismissed_at' => now()]);
    }
}
