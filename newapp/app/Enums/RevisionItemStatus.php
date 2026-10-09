<?php

namespace App\Enums;

enum RevisionItemStatus: string
{
    case Pending = 'PENDING';
    case Resolved = 'RESOLVED';
}
