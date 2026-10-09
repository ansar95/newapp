<?php

namespace App\Enums;

/**
 * Describes when a term may be billed. Informational only: readiness is always set manually.
 */
enum ProjectTermTriggerType: string
{
    case Manual = 'MANUAL';
    case Progress = 'PROGRESS';
    case Milestone = 'MILESTONE';
    case Date = 'DATE';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Progress => 'Progress Fisik',
            self::Milestone => 'Milestone',
            self::Date => 'Tanggal',
        };
    }
}
