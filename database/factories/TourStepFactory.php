<?php

namespace Arzcode\InfinitoOnboarding\Database\Factories;

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TourStep>
 */
class TourStepFactory extends Factory
{
    protected $model = TourStep::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'order' => 0,
            'target_type' => TargetType::DataTour,
            'target' => Str::slug(fake()->unique()->words(2, true)),
            'title' => fake()->sentence(3),
            'body' => '<p>' . fake()->sentence() . '</p>',
            'placement' => Placement::Auto,
            'extra' => null,
        ];
    }

    public function css(string $selector): static
    {
        return $this->state(fn () => ['target_type' => TargetType::Css, 'target' => $selector]);
    }

    public function untargeted(): static
    {
        return $this->state(fn () => ['target_type' => TargetType::None, 'target' => null]);
    }

    public function order(int $order): static
    {
        return $this->state(fn () => ['order' => $order]);
    }
}
