<?php

namespace App\Enums;

enum RevisionStatus: string
{
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
}
