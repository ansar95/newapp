<?php

namespace App\Enums;

/**
 * Who performed a document status change. Client actions are recorded by internal users for now.
 */
enum DocumentActorType: string
{
    case Internal = 'INTERNAL';
    case ClientUser = 'CLIENT_USER';
}
