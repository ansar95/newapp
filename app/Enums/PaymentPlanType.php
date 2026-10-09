<?php

namespace App\Enums;

enum PaymentPlanType: string
{
    case Planned = 'PLANNED';
    case ReadyToPay = 'READY_TO_PAY';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::ReadyToPay => 'Bisa Dibayar Langsung',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Planned => 'Pembayaran akan dilakukan pada tanggal tertentu.',
            self::ReadyToPay => 'Pembayaran siap diproses.',
        };
    }
}
