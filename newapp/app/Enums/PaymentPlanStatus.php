<?php

namespace App\Enums;

enum PaymentPlanStatus: string
{
    case Planned = 'PLANNED';
    case ReadyToPay = 'READY_TO_PAY';
    case Paid = 'PAID';
}
