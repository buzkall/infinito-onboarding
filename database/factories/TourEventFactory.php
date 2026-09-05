<?php

namespace Arzcode\InfinitoOnboarding\Database\Factories;

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourEvent>
 */
class TourEventFactory extends Factory
{
    protected $model = TourEvent::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'step_id' => null,
            'user_id' => (string) fake()->numberBetween(1, 1000),
            'tenant_id' => TourCompletion::NO_TENANT,
            'version' => '1',
            'event' => TourEventType::View,
            'meta' => null,
            'created_at' => now(),
        ];
    }

    public function type(TourEventType $type): static
    {
        return $this->state(fn () => ['event' => $type]);
    }
}
