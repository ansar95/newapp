<?php

namespace App\Enums;

/**
 * Morph aliases of entities a project document can reference.
 */
enum DocumentReferenceType: string
{
    case Billing = 'BILLING';

    public function label(): string
    {
        return match ($this) {
            self::Billing => 'Penagihan',
        };
    }
}
