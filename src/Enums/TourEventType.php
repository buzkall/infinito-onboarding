<?php

namespace Arzcode\InfinitoOnboarding\Enums;

enum TourEventType: string
{
    /** The tour (or changelog) was shown. */
    case View = 'view';

    /** A step was highlighted. */
    case Step = 'step';

    case Completed = 'completed';

    case Dismissed = 'dismissed';

    /** A step's target could not be found in the DOM and was skipped. */
    case TargetMissing = 'target_missing';

    /**
     * Events the browser is allowed to report through the overlay.
     *
     * @return array<int, string>
     */
    public static function reportable(): array
    {
        return [self::View->value, self::Step->value, self::TargetMissing->value];
    }
}
