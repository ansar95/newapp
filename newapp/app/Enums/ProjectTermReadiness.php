<?php

namespace App\Enums;

/**
 * The only manually stored part of a term's state; billing progress is computed from billings and payments.
 */
enum ProjectTermReadiness: string
{
    case NotReady = 'NOT_READY';
    case Ready = 'READY';
    case Cancelled = 'CANCELLED';
}
