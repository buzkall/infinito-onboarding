<?php

namespace Arzcode\InfinitoOnboarding\Enums;

use Filament\Support\Contracts\HasLabel;

enum Placement: string implements HasLabel
{
    case Auto = 'auto';
    case Top = 'top';
    case Right = 'right';
    case Bottom = 'bottom';
    case Left = 'left';
    case Over = 'over';

    public function getLabel(): string
    {
        return __('infinito-onboarding::onboarding.placements.' . $this->value);
    }
}
