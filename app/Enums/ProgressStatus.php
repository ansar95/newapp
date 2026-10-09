<?php

namespace App\Enums;

enum ProgressStatus: string
{
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
}
