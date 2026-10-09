<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case ProjectAdmin = 'project_admin';
    case BoqCostControl = 'boq_cost_control';
    case Approver = 'approver';
    case Finance = 'finance';
    case ProjectSite = 'project_site';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::ProjectAdmin => 'Project Admin',
            self::BoqCostControl => 'BOQ / Cost Control',
            self::Approver => 'Approver',
            self::Finance => 'Finance',
            self::ProjectSite => 'Project / Site',
        };
    }
}
