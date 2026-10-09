<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Confirm = 'confirm';
    case RequestRevision = 'request_revision';
}
