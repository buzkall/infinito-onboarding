<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Named, reusable audience segments.
 *
 * Defined in `config('infinito-onboarding.segments')` or through
 * `InfinitoOnboardingPlugin::make()->segment('beta', …)`. A segment is either
 * an array of audience criteria (`roles`, `permissions`, `users`) or a
 * callable receiving the user and returning a bool. Tours reference them
 * through `audience.segments`.
 */
class Segments
{
    /** @var array<string, array<string, mixed>|Closure> */
    protected array $segments = [];

    /**
     * @param  array<string, mixed>|Closure|callable  $definition
     */
    public function register(string $name, array|Closure|callable $definition): static
    {
        $this->segments[$name] = $definition instanceof Closure || is_array($definition)
            ? $definition
            : Closure::fromCallable($definition);

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>|Closure>
     */
    public function all(): array
    {
        $configured = config('infinito-onboarding.segments', []);
        $configured = is_array($configured) ? $configured : [];

        $segments = [];

        foreach ([...$configured, ...$this->segments] as $name => $definition) {
            if (is_array($definition)) {
                $segments[(string) $name] = $definition;
            } elseif ($definition instanceof Closure) {
                $segments[(string) $name] = $definition;
            } elseif (is_callable($definition)) {
                $segments[(string) $name] = Closure::fromCallable($definition);
            }
        }

        return $segments;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /**
     * Segment labels for the resource select, from `segment_labels` config.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $labels = config('infinito-onboarding.segment_labels', []);
        $labels = is_array($labels) ? $labels : [];

        $options = [];

        foreach ($this->names() as $name) {
            $options[$name] = (string) ($labels[$name] ?? Str::headline($name));
        }

        return $options;
    }

    /**
     * Whether the user belongs to the segment. Unknown segments never match.
     */
    public function matches(string $name, Authenticatable $user): bool
    {
        $definition = $this->all()[$name] ?? null;

        if ($definition === null) {
            return false;
        }

        if ($definition instanceof Closure) {
            return (bool) app()->call($definition, ['user' => $user]);
        }

        return app(TourResolver::class)->passesCriteria($definition, $user);
    }

    public function flush(): static
    {
        $this->segments = [];

        return $this;
    }
}
