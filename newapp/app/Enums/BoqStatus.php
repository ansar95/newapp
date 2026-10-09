<?php

namespace App\Enums;

enum BoqStatus: string
{
    case Draft = 'DRAFT';
    case WaitingApproval = 'WAITING_APPROVAL';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Approved = 'APPROVED';
}
