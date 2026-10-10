<?php

namespace App\Enums;

/**
 * Cost variance category based on actual cost (BMHP + rental + paid payments) against the approved BOQ value.
 */
enum ProjectVarianceStatus: string
{
    case OverBudget = 'OVER_BUDGET';
    case Warning = 'WARNING';
    case Normal = 'NORMAL';

    public const WARNING_PERCENTAGE = 90;

    public function label(): string
    {
        return match ($this) {
            self::OverBudget => 'Over Budget',
            self::Warning => 'Mendekati',
            self::Normal => 'Normal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OverBudget => 'Actual cost melebihi 100% BOQ',
            self::Warning => 'Actual cost '.self::WARNING_PERCENTAGE.'–100% BOQ',
            self::Normal => 'Actual cost di bawah '.self::WARNING_PERCENTAGE.'% BOQ',
        };
    }

    /**
     * Compared in cents so the 90% and 100% boundaries are exact.
     */
    public static function fromAmounts(float $boqValue, float $actualCost): self
    {
        $boqCents = (int) round($boqValue * 100);
        $actualCents = (int) round($actualCost * 100);

        if ($actualCents > $boqCents) {
            return self::OverBudget;
        }

        if ($actualCents * 100 >= $boqCents * self::WARNING_PERCENTAGE) {
            return self::Warning;
        }

        return self::Normal;
    }
}
