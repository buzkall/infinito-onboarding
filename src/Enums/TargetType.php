<?php

namespace Arzcode\InfinitoOnboarding\Enums;

use Filament\Support\Contracts\HasLabel;

enum TargetType: string implements HasLabel
{
    case DataTour = 'data_tour';
    case Css = 'css';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::DataTour => __('infinito-onboarding::onboarding.target_types.data_tour'),
            self::Css => __('infinito-onboarding::onboarding.target_types.css'),
            self::None => __('infinito-onboarding::onboarding.target_types.none'),
        };
    }

    public function requiresTarget(): bool
    {
        return $this !== self::None;
    }

    /**
     * Turn the stored target into the CSS selector the browser will query.
     */
    public function toSelector(?string $target): ?string
    {
        if ($this === self::None || blank($target)) {
            return null;
        }

        return match ($this) {
            self::DataTour => '[data-tour="' . addslashes($target) . '"]',
            self::Css => $target,
        };
    }
}
