<?php

namespace App\Auth;

use App\Enums\Permission;
use App\Enums\UserRole;

class RolePermissions
{
    /**
     * @return list<Permission>
     */
    public static function for(UserRole $role): array
    {
        return match ($role) {
            UserRole::Superadmin => Permission::cases(),
            UserRole::ProjectAdmin => [
                Permission::ProjectView,
                Permission::ProjectCreate,
                Permission::ProjectUpdate,
                Permission::BoqView,
                Permission::BoqCreate,
                Permission::BoqUpdate,
                Permission::BoqSubmit,
                Permission::RevisionView,
                Permission::RevisionUpdate,
                Permission::RevisionSubmit,
                Permission::PaymentPlanView,
                Permission::ProgressView,
                Permission::AssetView,
                Permission::AssetCreate,
                Permission::AssetUpdate,
                Permission::AssetAssign,
                Permission::AssetRelease,
                Permission::AssetManageMaster,
                Permission::AssetManageRequest,
                Permission::ProcurementView,
                Permission::ProcurementCreate,
                Permission::ProcurementUpdate,
                Permission::ProcurementSubmit,
                Permission::ProcurementAllocate,
                Permission::BillingView,
                Permission::BillingCreate,
                Permission::BillingUpdate,
                Permission::BillingSubmit,
                Permission::BillingTermManage,
                Permission::BillingPaymentView,
                ...self::allDocumentPermissions(),
                Permission::ReportView,
            ],
            UserRole::BoqCostControl => [
                Permission::ProjectView,
                Permission::BoqView,
                Permission::BoqCreate,
                Permission::BoqUpdate,
                Permission::BoqSubmit,
                Permission::RevisionView,
                Permission::RevisionCreate,
                Permission::RevisionUpdate,
                Permission::RevisionSubmit,
                Permission::PaymentPlanView,
                Permission::ProgressView,
                Permission::AssetView,
                Permission::ProcurementView,
                Permission::ProcurementAllocate,
                Permission::BillingView,
                Permission::DocumentView,
                Permission::ReportView,
            ],
            UserRole::Approver => [
                Permission::ProjectView,
                Permission::BoqView,
                Permission::BoqReview,
                Permission::BoqApprove,
                Permission::BoqRequestRevision,
                Permission::RevisionView,
                Permission::RevisionCreate,
                Permission::PaymentPlanView,
                Permission::ProgressView,
                Permission::AssetView,
                Permission::ProcurementView,
                Permission::ProcurementApprove,
                Permission::BillingView,
                Permission::BillingPaymentView,
                Permission::DocumentView,
                Permission::DocumentDownload,
                Permission::ReportView,
            ],
            UserRole::Finance => [
                Permission::ProjectView,
                Permission::BoqView,
                Permission::PaymentPlanView,
                Permission::PaymentPlanCreate,
                Permission::PaymentPlanUpdate,
                Permission::PaymentProcess,
                Permission::ProgressView,
                Permission::AssetView,
                Permission::ProcurementView,
                Permission::ProcurementPay,
                Permission::ProcurementCancel,
                Permission::BillingView,
                Permission::BillingCreate,
                Permission::BillingUpdate,
                Permission::BillingSubmit,
                Permission::BillingSend,
                Permission::BillingCancel,
                Permission::BillingPaymentView,
                Permission::BillingPaymentCreate,
                Permission::BillingPaymentCancel,
                Permission::BillingTermManage,
                ...self::allDocumentPermissions(),
                Permission::ReportView,
            ],
            UserRole::ProjectSite => [
                Permission::ProjectView,
                Permission::BoqView,
                Permission::PaymentPlanView,
                Permission::ProgressView,
                Permission::ProgressUpdate,
                Permission::AssetView,
                Permission::ProcurementView,
                Permission::ProcurementCreate,
                Permission::ProcurementUpdate,
                Permission::ProcurementSubmit,
                Permission::ProcurementAllocate,
                Permission::ReportView,
            ],
        };
    }

    /**
     * @return list<Permission>
     */
    private static function allDocumentPermissions(): array
    {
        return [
            Permission::DocumentView,
            Permission::DocumentCreate,
            Permission::DocumentUpdate,
            Permission::DocumentSubmit,
            Permission::DocumentRevision,
            Permission::DocumentResubmit,
            Permission::DocumentApprove,
            Permission::DocumentCancel,
            Permission::DocumentDownload,
        ];
    }

    public static function roleHas(UserRole $role, Permission $permission): bool
    {
        foreach (self::for($role) as $allowed) {
            if ($allowed === $permission) {
                return true;
            }
        }

        return false;
    }
}
