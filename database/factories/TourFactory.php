<?php

namespace Arzcode\InfinitoOnboarding\Database\Factories;

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'mode' => TourMode::Tour,
            'route_pattern' => null,
            'version' => '1',
            'published_at' => now()->subDay(),
            'starts_at' => null,
            'ends_at' => null,
            'audience' => null,
            'tenant_id' => null,
            'sort' => 0,
            'is_active' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function changelog(): static
    {
        return $this->state(fn () => ['mode' => TourMode::Changelog]);
    }

    public function hints(): static
    {
        return $this->state(fn () => ['mode' => TourMode::Hint]);
    }

    public function forRoute(string $pattern): static
    {
        return $this->state(fn () => ['route_pattern' => $pattern]);
    }

    public function forTenant(?string $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }

    public function version(string $version): static
    {
        return $this->state(fn () => ['version' => $version]);
    }

    /**
     * @param  array<string, mixed>  $audience
     */
    public function audience(array $audience): static
    {
        return $this->state(fn () => ['audience' => $audience]);
    }
}
