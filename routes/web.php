<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\BmhpPurchaseController;
use App\Http\Controllers\BmhpPurchaseDetailController;
use App\Http\Controllers\BoqController;
use App\Http\Controllers\BoqReviewController;
use App\Http\Controllers\BoqRevisionController;
use App\Http\Controllers\ClientBillingController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterBmhpController;
use App\Http\Controllers\MasterBmhpImportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ProjectAssetController;
use App\Http\Controllers\ProjectBillingController;
use App\Http\Controllers\ProjectBmhpController;
use App\Http\Controllers\ProjectBoqController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectDocumentVersionController;
use App\Http\Controllers\ProjectDocumentWorkflowController;
use App\Http\Controllers\ProjectTermController;
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

    Route::get('/projects/{project}/bmhp', [ProjectBmhpController::class, 'index'])->name('projects.bmhp.index');
    Route::get('/projects/{project}/bmhp/create', [ProjectBmhpController::class, 'create'])->name('projects.bmhp.create');
    Route::post('/projects/{project}/bmhp', [ProjectBmhpController::class, 'store'])->name('projects.bmhp.store');

    Route::get('/bmhp-purchases/{bmhpPurchase}', [BmhpPurchaseController::class, 'show'])->name('bmhp-purchases.show');
    Route::get('/bmhp-purchases/{bmhpPurchase}/edit', [BmhpPurchaseController::class, 'edit'])->name('bmhp-purchases.edit');
    Route::put('/bmhp-purchases/{bmhpPurchase}', [BmhpPurchaseController::class, 'update'])->name('bmhp-purchases.update');
    Route::post('/bmhp-purchases/{bmhpPurchase}/submit', [BmhpPurchaseController::class, 'submit'])->name('bmhp-purchases.submit');
    Route::post('/bmhp-purchases/{bmhpPurchase}/confirm', [BmhpPurchaseController::class, 'confirm'])->name('bmhp-purchases.confirm');
    Route::post('/bmhp-purchases/{bmhpPurchase}/return-to-draft', [BmhpPurchaseController::class, 'returnToDraft'])->name('bmhp-purchases.return-to-draft');
    Route::post('/bmhp-purchases/{bmhpPurchase}/cancel', [BmhpPurchaseController::class, 'cancel'])->name('bmhp-purchases.cancel');
    Route::get('/bmhp-purchases/{bmhpPurchase}/receipt', [BmhpPurchaseController::class, 'receipt'])->name('bmhp-purchases.receipt');
    Route::post('/bmhp-purchases/{bmhpPurchase}/details', [BmhpPurchaseDetailController::class, 'store'])->name('bmhp-purchases.details.store');
    Route::patch('/bmhp-purchase-details/{detail}', [BmhpPurchaseDetailController::class, 'update'])->name('bmhp-purchase-details.update');
    Route::delete('/bmhp-purchase-details/{detail}', [BmhpPurchaseDetailController::class, 'destroy'])->name('bmhp-purchase-details.destroy');
    Route::put('/bmhp-purchase-details/{detail}/allocations', [BmhpPurchaseDetailController::class, 'allocate'])->name('bmhp-purchase-details.allocate');

    Route::get('/master-bmhp', [MasterBmhpController::class, 'index'])->name('master-bmhps.index');
    Route::get('/master-bmhp/create', [MasterBmhpController::class, 'create'])->name('master-bmhps.create');
    Route::get('/master-bmhp/import', [MasterBmhpImportController::class, 'show'])->name('master-bmhps.import');
    Route::get('/master-bmhp/import/template', [MasterBmhpImportController::class, 'template'])->name('master-bmhps.import.template');
    Route::post('/master-bmhp/import', [MasterBmhpImportController::class, 'preview'])->name('master-bmhps.import.preview');
    Route::post('/master-bmhp/import/confirm', [MasterBmhpImportController::class, 'confirm'])->name('master-bmhps.import.confirm');
    Route::post('/master-bmhp/import/cancel', [MasterBmhpImportController::class, 'cancel'])->name('master-bmhps.import.cancel');
    Route::post('/master-bmhp', [MasterBmhpController::class, 'store'])->name('master-bmhps.store');
    Route::get('/master-bmhp/{masterBmhp}/edit', [MasterBmhpController::class, 'edit'])->name('master-bmhps.edit');
    Route::put('/master-bmhp/{masterBmhp}', [MasterBmhpController::class, 'update'])->name('master-bmhps.update');
    Route::delete('/master-bmhp/{masterBmhp}', [MasterBmhpController::class, 'destroy'])->name('master-bmhps.destroy');

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

    Route::get('/boqs/{boq}', [BoqController::class, 'show'])->name('boqs.show');
    Route::post('/boqs/{boq}/submit', [BoqController::class, 'submit'])->name('boqs.submit');
    Route::post('/boqs/{boq}/cogs', [BoqController::class, 'storeCogs'])->name('boqs.cogs.store');
    Route::get('/boqs/{boq}/cogs/export', [BoqController::class, 'exportCogs'])->name('boqs.cogs.export');
    Route::post('/boqs/{boq}/cogs/import', [BoqController::class, 'importCogs'])->name('boqs.cogs.import');
    Route::get('/boq-cogs/template', [BoqController::class, 'cogsTemplate'])->name('boqs.cogs.template');
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

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
