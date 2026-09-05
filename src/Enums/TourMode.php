<?php

namespace Arzcode\InfinitoOnboarding\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TourMode: string implements HasColor, HasLabel
{
    case Tour = 'tour';
    case Changelog = 'changelog';

    /** Persistent pulsing dots next to each target; each opens its step on demand. */
    case Hint = 'hint';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tour => __('infinito-onboarding::onboarding.modes.tour'),
            self::Changelog => __('infinito-onboarding::onboarding.modes.changelog'),
            self::Hint => __('infinito-onboarding::onboarding.modes.hint'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tour => 'primary',
            self::Changelog => 'info',
            self::Hint => 'warning',
        };
    }
}
