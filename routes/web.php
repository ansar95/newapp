<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\BoqController;
use App\Http\Controllers\BoqReviewController;
use App\Http\Controllers\BoqRevisionController;
use App\Http\Controllers\ClientBillingController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManPowerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentPlanController;
use App\Http\Controllers\ProcurementAllocationController;
use App\Http\Controllers\ProcurementRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ProjectAssetController;
use App\Http\Controllers\ProjectBillingController;
use App\Http\Controllers\ProjectBoqController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectDocumentVersionController;
use App\Http\Controllers\ProjectDocumentWorkflowController;
use App\Http\Controllers\ProjectManPowerController;
use App\Http\Controllers\ProjectProcurementController;
use App\Http\Controllers\ProjectReportController;
use App\Http\Controllers\ProjectTermController;
use App\Http\Controllers\ProjectTransferController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/projects', [ReportController::class, 'projects'])->name('reports.projects');
    Route::get('/reports/projects/export', [ReportController::class, 'exportProjects'])->name('reports.projects.export');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/reports/finance/export', [ReportController::class, 'exportFinance'])->name('reports.finance.export');
    Route::get('/reports/progress', [ReportController::class, 'progress'])->name('reports.progress');
    Route::get('/reports/progress/export', [ReportController::class, 'exportProgress'])->name('reports.progress.export');
    Route::get('/reports/variance', [ReportController::class, 'variance'])->name('reports.variance');
    Route::get('/reports/assets', [ReportController::class, 'assets'])->name('reports.assets');
    Route::get('/reports/assets/export', [ReportController::class, 'exportAssets'])->name('reports.assets.export');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/import', [ProjectTransferController::class, 'import'])->name('projects.import');
    Route::get('/projects/{project}/export', [ProjectTransferController::class, 'export'])->name('projects.export');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/activate', [ProjectController::class, 'activate'])->name('projects.activate');
    Route::post('/projects/{project}/complete', [ProjectController::class, 'complete'])->name('projects.complete');

    Route::get('/projects/{project}/boq', [ProjectBoqController::class, 'index'])->name('projects.boq.index');
    Route::get('/projects/{project}/boq/create', [ProjectBoqController::class, 'create'])->name('projects.boq.create');
    Route::post('/projects/{project}/boq', [ProjectBoqController::class, 'store'])->name('projects.boq.store');

    Route::get('/projects/{project}/assets', [ProjectAssetController::class, 'index'])->name('projects.assets.index');
    Route::post('/projects/{project}/assets', [ProjectAssetController::class, 'store'])->name('projects.assets.store');
    Route::post('/projects/{project}/assets/{projectAsset}/release', [ProjectAssetController::class, 'release'])->name('projects.assets.release');
    Route::patch('/projects/{project}/assets/{projectAsset}/responsible', [ProjectAssetController::class, 'updateResponsible'])->name('projects.assets.responsible');

    Route::get('/projects/{project}/man-power', [ProjectManPowerController::class, 'index'])->name('projects.man-power.index');
    Route::post('/projects/{project}/man-power', [ProjectManPowerController::class, 'store'])->name('projects.man-power.store');
    Route::put('/project-man-powers/{projectManPower}', [ProjectManPowerController::class, 'update'])->name('project-man-powers.update');
    Route::post('/project-man-powers/{projectManPower}/transactions', [ProjectManPowerController::class, 'storeTransaction'])->name('man-power-transactions.store');
    Route::post('/man-power-transactions/{manPowerTransaction}/cancel', [ProjectManPowerController::class, 'cancelTransaction'])->name('man-power-transactions.cancel');

    Route::get('/projects/{project}/procurement', [ProjectProcurementController::class, 'index'])->name('projects.procurement.index');
    Route::get('/projects/{project}/procurement/create', [ProjectProcurementController::class, 'create'])->name('projects.procurement.create');
    Route::post('/projects/{project}/procurement', [ProjectProcurementController::class, 'store'])->name('projects.procurement.store');
    Route::get('/projects/{project}/bmhp', [ProcurementAllocationController::class, 'bmhp'])->name('projects.bmhp.index');
    Route::get('/projects/{project}/rentals', [ProcurementAllocationController::class, 'rentals'])->name('projects.rentals.index');

    Route::get('/procurement-requests/{procurementRequest}', [ProcurementRequestController::class, 'show'])->name('procurement-requests.show');
    Route::get('/procurement-requests/{procurementRequest}/edit', [ProcurementRequestController::class, 'edit'])->name('procurement-requests.edit');
    Route::put('/procurement-requests/{procurementRequest}', [ProcurementRequestController::class, 'update'])->name('procurement-requests.update');
    Route::post('/procurement-requests/{procurementRequest}/submit', [ProcurementRequestController::class, 'submit'])->name('procurement-requests.submit');
    Route::post('/procurement-requests/{procurementRequest}/approve', [ProcurementRequestController::class, 'approve'])->name('procurement-requests.approve');
    Route::post('/procurement-requests/{procurementRequest}/return-to-draft', [ProcurementRequestController::class, 'returnToDraft'])->name('procurement-requests.return-to-draft');
    Route::post('/procurement-requests/{procurementRequest}/cancel', [ProcurementRequestController::class, 'cancel'])->name('procurement-requests.cancel');
    Route::get('/procurement-requests/{procurementRequest}/pay', [ProcurementRequestController::class, 'payForm'])->name('procurement-requests.pay');
    Route::post('/procurement-requests/{procurementRequest}/pay', [ProcurementRequestController::class, 'pay'])->name('procurement-requests.pay.store');
    Route::get('/procurement-requests/{procurementRequest}/payment-proof', [ProcurementRequestController::class, 'paymentProof'])->name('procurement-requests.payment-proof');
    Route::put('/procurement-request-items/{procurementRequestItem}/allocations', [ProcurementAllocationController::class, 'update'])->name('procurement-request-items.allocate');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/export', [AssetController::class, 'export'])->name('assets.export');
    Route::get('/assets/import/template', [AssetController::class, 'importTemplate'])->name('assets.import.template');
    Route::post('/assets/import', [AssetController::class, 'import'])->name('assets.import');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::post('/assets/{asset}/available', [AssetController::class, 'markAvailable'])->name('assets.available');
    Route::get('/assets/{asset}/request-letter', [AssetController::class, 'downloadRequestLetter'])->name('assets.request-letter');

    Route::get('/man-powers', [ManPowerController::class, 'index'])->name('man-powers.index');
    Route::post('/man-powers', [ManPowerController::class, 'store'])->name('man-powers.store');
    Route::get('/man-powers/export', [ManPowerController::class, 'export'])->name('man-powers.export');
    Route::get('/man-powers/import/template', [ManPowerController::class, 'importTemplate'])->name('man-powers.import.template');
    Route::post('/man-powers/import', [ManPowerController::class, 'import'])->name('man-powers.import');
    Route::get('/man-powers/{manPower}/edit', [ManPowerController::class, 'edit'])->name('man-powers.edit');
    Route::put('/man-powers/{manPower}', [ManPowerController::class, 'update'])->name('man-powers.update');

    Route::get('/boqs/{boq}', [BoqController::class, 'show'])->name('boqs.show');
    Route::post('/boqs/{boq}/submit', [BoqController::class, 'submit'])->name('boqs.submit');
    Route::post('/boqs/{boq}/cogs', [BoqController::class, 'storeCogs'])->name('boqs.cogs.store');
    Route::get('/boqs/{boq}/cogs/export', [BoqController::class, 'exportCogs'])->name('boqs.cogs.export');
    Route::post('/boqs/{boq}/cogs/import', [BoqController::class, 'importCogs'])->name('boqs.cogs.import');
    Route::get('/boq-cogs/template', [BoqController::class, 'cogsTemplate'])->name('boqs.cogs.template');
    Route::post('/boq-cogs/parse', [BoqController::class, 'parseCogsFile'])->name('boqs.cogs.parse');
    Route::post('/boq-cogs/form-export', [BoqController::class, 'exportCogsForm'])->name('boqs.cogs.form-export');
    Route::post('/boq-cogs/{boqCogs}/items', [BoqController::class, 'storeItem'])->name('boqs.items.store');
    Route::patch('/boq-items/{item}', [BoqController::class, 'updateItem'])->name('boqs.items.update');

    Route::get('/boq-approvals', [BoqReviewController::class, 'index'])->name('boqs.approvals');
    Route::get('/boqs/{boq}/review', [BoqReviewController::class, 'show'])->name('boqs.review');
    Route::post('/boqs/{boq}/confirm', [BoqReviewController::class, 'confirm'])->name('boqs.confirm');
    Route::post('/boqs/{boq}/request-revision', [BoqReviewController::class, 'requestRevision'])->name('boqs.request-revision');

    Route::get('/boqs/{boq}/revision', [BoqRevisionController::class, 'show'])->name('boqs.revision.show');
    Route::post('/boqs/{boq}/revision/prepare', [BoqRevisionController::class, 'prepare'])->name('boqs.revision.prepare');
    Route::post('/boqs/{boq}/revision/submit', [BoqRevisionController::class, 'submit'])->name('boqs.revision.submit');
    Route::patch('/boq-items/{item}/revision', [BoqRevisionController::class, 'updateItem'])->name('boqs.revision.items.update');

    Route::get('/projects/{project}/billing', [ProjectBillingController::class, 'index'])->name('projects.billing.index');
    Route::post('/projects/{project}/terms', [ProjectTermController::class, 'store'])->name('project-terms.store');
    Route::put('/project-terms/{projectTerm}', [ProjectTermController::class, 'update'])->name('project-terms.update');
    Route::post('/project-terms/{projectTerm}/readiness', [ProjectTermController::class, 'readiness'])->name('project-terms.readiness');
    Route::delete('/project-terms/{projectTerm}', [ProjectTermController::class, 'destroy'])->name('project-terms.destroy');

    Route::get('/projects/{project}/billings/create', [ClientBillingController::class, 'create'])->name('client-billings.create');
    Route::post('/projects/{project}/billings', [ClientBillingController::class, 'store'])->name('client-billings.store');
    Route::get('/client-billings/{clientBilling}', [ClientBillingController::class, 'show'])->name('client-billings.show');
    Route::get('/client-billings/{clientBilling}/edit', [ClientBillingController::class, 'edit'])->name('client-billings.edit');
    Route::put('/client-billings/{clientBilling}', [ClientBillingController::class, 'update'])->name('client-billings.update');
    Route::post('/client-billings/{clientBilling}/submit', [ClientBillingController::class, 'submit'])->name('client-billings.submit');
    Route::post('/client-billings/{clientBilling}/return-to-draft', [ClientBillingController::class, 'returnToDraft'])->name('client-billings.return-to-draft');
    Route::post('/client-billings/{clientBilling}/send', [ClientBillingController::class, 'send'])->name('client-billings.send');
    Route::post('/client-billings/{clientBilling}/cancel', [ClientBillingController::class, 'cancel'])->name('client-billings.cancel');
    Route::get('/client-billings/{clientBilling}/attachment', [ClientBillingController::class, 'attachment'])->name('client-billings.attachment');

    Route::post('/client-billings/{clientBilling}/payments', [ClientPaymentController::class, 'store'])->name('client-payments.store');
    Route::post('/client-payments/{clientPayment}/cancel', [ClientPaymentController::class, 'cancel'])->name('client-payments.cancel');
    Route::get('/client-payments/{clientPayment}/proof', [ClientPaymentController::class, 'proof'])->name('client-payments.proof');

    Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'index'])->name('projects.documents.index');
    Route::get('/projects/{project}/documents/create', [ProjectDocumentController::class, 'create'])->name('project-documents.create');
    Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('project-documents.store');
    Route::get('/project-documents/{projectDocument}', [ProjectDocumentController::class, 'show'])->name('project-documents.show');
    Route::get('/project-documents/{projectDocument}/edit', [ProjectDocumentController::class, 'edit'])->name('project-documents.edit');
    Route::put('/project-documents/{projectDocument}', [ProjectDocumentController::class, 'update'])->name('project-documents.update');
    Route::post('/project-documents/{projectDocument}/versions', [ProjectDocumentVersionController::class, 'store'])->name('project-document-versions.store');
    Route::post('/project-documents/{projectDocument}/submit', [ProjectDocumentWorkflowController::class, 'submit'])->name('project-documents.submit');
    Route::post('/project-documents/{projectDocument}/start-review', [ProjectDocumentWorkflowController::class, 'startReview'])->name('project-documents.start-review');
    Route::post('/project-documents/{projectDocument}/approve', [ProjectDocumentWorkflowController::class, 'approve'])->name('project-documents.approve');
    Route::post('/project-documents/{projectDocument}/request-revision', [ProjectDocumentWorkflowController::class, 'requestRevision'])->name('project-documents.request-revision');
    Route::post('/project-documents/{projectDocument}/resubmit', [ProjectDocumentWorkflowController::class, 'resubmit'])->name('project-documents.resubmit');
    Route::post('/project-documents/{projectDocument}/cancel', [ProjectDocumentWorkflowController::class, 'cancel'])->name('project-documents.cancel');
    Route::get('/project-document-versions/{projectDocumentVersion}/view', [ProjectDocumentVersionController::class, 'view'])->name('project-document-versions.view');
    Route::get('/project-document-versions/{projectDocumentVersion}/download', [ProjectDocumentVersionController::class, 'download'])->name('project-document-versions.download');

    Route::get('/projects/{project}/payment', [PaymentPlanController::class, 'index'])->name('projects.payment.index');
    Route::get('/projects/{project}/payments', [PaymentPlanController::class, 'index'])->name('projects.payments.index');
    Route::post('/projects/{project}/payment-plans', [PaymentPlanController::class, 'store'])->name('payment-plans.store');
    Route::post('/payment-plans/{paymentPlan}/ready', [PaymentPlanController::class, 'markReady'])->name('payment-plans.ready');

    Route::get('/payment-plans/{paymentPlan}/pay', [PaymentController::class, 'create'])->name('payments.pay');
    Route::post('/payment-plans/{paymentPlan}/pay', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    Route::get('/projects/{project}/progress', [ProgressController::class, 'index'])->name('projects.progress.index');
    Route::patch('/boq-items/{item}/progress', [ProgressController::class, 'update'])->name('progress.update');

    Route::get('/projects/{project}/reports', [ProjectReportController::class, 'index'])->name('projects.reports.index');
    Route::get('/projects/{project}/reports/export', [ProjectReportController::class, 'export'])->name('projects.reports.export');
    Route::get('/projects/{project}/reports/print', [ProjectReportController::class, 'print'])->name('projects.reports.print');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
