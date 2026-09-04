<?php

namespace Arzcode\InfinitoOnboarding\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TourMode: string implements HasColor, HasLabel
{
    case Tour = 'tour';
    case Changelog = 'changelog';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tour => __('infinito-onboarding::onboarding.modes.tour'),
            self::Changelog => __('infinito-onboarding::onboarding.modes.changelog'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tour => 'primary',
            self::Changelog => 'info',
        };
    }
}
