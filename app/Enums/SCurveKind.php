<?php

namespace App\Enums;

enum SCurveKind: string
{
    case Initial = 'initial';
    case Rebaseline = 'rebaseline';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Rencana awal (P0)',
            self::Rebaseline => 'Rebaseline (P1)',
        };
    }
}
