<?php

namespace App\Enums;

enum Permission: string
{
    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';

    case BoqView = 'boq.view';
    case BoqCreate = 'boq.create';
    case BoqUpdate = 'boq.update';
    case BoqSubmit = 'boq.submit';
    case BoqReview = 'boq.review';
    case BoqApprove = 'boq.approve';
    case BoqRequestRevision = 'boq.request_revision';

    case RevisionView = 'revision.view';
    case RevisionCreate = 'revision.create';
    case RevisionUpdate = 'revision.update';
    case RevisionSubmit = 'revision.submit';

    case PaymentPlanView = 'payment_plan.view';
    case PaymentPlanCreate = 'payment_plan.create';
    case PaymentPlanUpdate = 'payment_plan.update';
    case PaymentProcess = 'payment.process';

    case ProgressView = 'progress.view';
    case ProgressUpdate = 'progress.update';

    case AssetView = 'asset.view';
    case AssetCreate = 'asset.create';
    case AssetUpdate = 'asset.update';
    case AssetAssign = 'asset.assign';
    case AssetRelease = 'asset.release';
    case AssetManageMaster = 'asset.manage_master';
    case AssetManageRequest = 'asset.manage_request';

    case ProcurementView = 'procurement.view';
    case ProcurementCreate = 'procurement.create';
    case ProcurementUpdate = 'procurement.update';
    case ProcurementSubmit = 'procurement.submit';
    case ProcurementApprove = 'procurement.approve';
    case ProcurementPay = 'procurement.pay';
    case ProcurementCancel = 'procurement.cancel';
    case ProcurementAllocate = 'procurement.allocate';

    case BillingView = 'billing.view';
    case BillingCreate = 'billing.create';
    case BillingUpdate = 'billing.update';
    case BillingSubmit = 'billing.submit';
    case BillingSend = 'billing.send';
    case BillingCancel = 'billing.cancel';
    case BillingPaymentView = 'billing.payment.view';
    case BillingPaymentCreate = 'billing.payment.create';
    case BillingPaymentCancel = 'billing.payment.cancel';
    case BillingTermManage = 'billing.term.manage';

    case DocumentView = 'document.view';
    case DocumentCreate = 'document.create';
    case DocumentUpdate = 'document.update';
    case DocumentSubmit = 'document.submit';
    case DocumentRevision = 'document.revision';
    case DocumentResubmit = 'document.resubmit';
    case DocumentApprove = 'document.approve';
    case DocumentCancel = 'document.cancel';
    case DocumentDownload = 'document.download';

    case ManPowerView = 'man_power.view';
    case ManPowerManage = 'man_power.manage';
    case ManPowerPay = 'man_power.pay';

    case ReportView = 'report.view';
}
