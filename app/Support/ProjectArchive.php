<?php

namespace App\Support;

use App\Models\ProjectDocumentVersion;

/**
 * Layout of the project export ZIP: project.json (all rows) + files/{disk}/{path} (attachments).
 *
 * Tables are listed in insert order. "shared" tables are master data matched by code on import
 * instead of being duplicated.
 */
final class ProjectArchive
{
    public const FORMAT = 'pm-cost-control-project';

    public const VERSION = 1;

    public const MANIFEST = 'project.json';

    public const FILES_DIR = 'files';

    /**
     * @return array<string, array{
     *     scope: array{0: string, 1: string}|null,
     *     parents: array<string, string>,
     *     optional_parents?: array<string, string>,
     *     users: list<string>,
     *     files: array<string, string>,
     *     unique: array<string, string>,
     *     shared?: string
     * }>
     */
    public static function tables(): array
    {
        return [
            'projects' => [
                'scope' => null,
                'parents' => [],
                'users' => ['created_by'],
                'files' => [],
                'unique' => ['code' => 'Kode project'],
            ],
            'boqs' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects'],
                'users' => ['submitted_by', 'reviewed_by'],
                'files' => [],
                'unique' => [],
            ],
            'boqs_cogs' => [
                'scope' => ['boq_id', 'boqs'],
                'parents' => ['boq_id' => 'boqs'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'boq_items' => [
                'scope' => ['boq_cogs_id', 'boqs_cogs'],
                'parents' => ['boq_cogs_id' => 'boqs_cogs'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'boq_revisions' => [
                'scope' => ['boq_id', 'boqs'],
                'parents' => ['boq_id' => 'boqs'],
                'users' => ['created_by', 'reviewed_by'],
                'files' => [],
                'unique' => ['revision_code' => 'Kode revisi BOQ'],
            ],
            'boq_revision_items' => [
                'scope' => ['boq_revision_id', 'boq_revisions'],
                'parents' => ['boq_revision_id' => 'boq_revisions', 'boq_item_id' => 'boq_items'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'payment_plans' => [
                'scope' => ['boq_id', 'boqs'],
                'parents' => ['boq_id' => 'boqs'],
                'users' => ['created_by'],
                'files' => [],
                'unique' => ['reference_code' => 'Kode payment plan'],
            ],
            'payment_plan_items' => [
                'scope' => ['payment_plan_id', 'payment_plans'],
                'parents' => ['payment_plan_id' => 'payment_plans', 'boq_item_id' => 'boq_items'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'payments' => [
                'scope' => ['payment_plan_id', 'payment_plans'],
                'parents' => ['payment_plan_id' => 'payment_plans'],
                'users' => ['paid_by', 'voided_by'],
                'files' => [],
                'unique' => ['payment_code' => 'Kode payment'],
            ],
            'payment_allocations' => [
                'scope' => ['payment_id', 'payments'],
                'parents' => ['payment_id' => 'payments', 'boq_item_id' => 'boq_items'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'procurement_requests' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects'],
                'users' => ['created_by', 'submitted_by', 'approved_by', 'returned_by', 'paid_by', 'cancelled_by'],
                'files' => ['payment_proof_file' => 'public'],
                'unique' => ['request_number' => 'Nomor permintaan pengadaan'],
            ],
            'procurement_request_items' => [
                'scope' => ['procurement_request_id', 'procurement_requests'],
                'parents' => ['procurement_request_id' => 'procurement_requests'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'procurement_allocations' => [
                'scope' => ['procurement_request_item_id', 'procurement_request_items'],
                'parents' => ['procurement_request_item_id' => 'procurement_request_items', 'boq_item_id' => 'boq_items'],
                'users' => [],
                'files' => [],
                'unique' => [],
            ],
            'assets' => [
                'scope' => null,
                'parents' => [],
                'optional_parents' => ['procurement_request_item_id' => 'procurement_request_items'],
                'users' => ['created_by', 'requested_by'],
                'files' => ['request_letter_file' => 'public'],
                'unique' => [],
                'shared' => 'code',
            ],
            'project_assets' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects', 'asset_id' => 'assets'],
                'users' => ['assigned_by', 'released_by'],
                'files' => [],
                'unique' => [],
            ],
            'project_terms' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects'],
                'users' => ['created_by'],
                'files' => [],
                'unique' => [],
            ],
            'client_billings' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects', 'project_term_id' => 'project_terms'],
                'users' => ['created_by', 'submitted_by', 'sent_by', 'cancelled_by'],
                'files' => ['attachment' => 'public'],
                'unique' => ['billing_number' => 'Nomor penagihan', 'invoice_number' => 'Nomor invoice'],
            ],
            'client_payments' => [
                'scope' => ['client_billing_id', 'client_billings'],
                'parents' => ['client_billing_id' => 'client_billings'],
                'users' => ['created_by', 'cancelled_by'],
                'files' => ['proof_file' => 'public'],
                'unique' => ['payment_number' => 'Nomor penerimaan client'],
            ],
            'document_types' => [
                'scope' => null,
                'parents' => [],
                'users' => [],
                'files' => [],
                'unique' => [],
                'shared' => 'code',
            ],
            'project_documents' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects', 'document_type_id' => 'document_types'],
                'users' => ['approved_by', 'cancelled_by', 'created_by'],
                'files' => [],
                'unique' => ['document_number' => 'Nomor dokumen'],
            ],
            'project_document_versions' => [
                'scope' => ['project_document_id', 'project_documents'],
                'parents' => ['project_document_id' => 'project_documents'],
                'users' => ['uploaded_by'],
                'files' => ['file_path' => ProjectDocumentVersion::DISK],
                'unique' => [],
            ],
            'project_document_status_histories' => [
                'scope' => ['project_document_id', 'project_documents'],
                'parents' => ['project_document_id' => 'project_documents'],
                'users' => ['changed_by'],
                'files' => [],
                'unique' => [],
            ],
            'man_powers' => [
                'scope' => null,
                'parents' => [],
                'users' => ['created_by'],
                'files' => [],
                'unique' => [],
                'shared' => 'code',
            ],
            'project_man_powers' => [
                'scope' => ['project_id', 'projects'],
                'parents' => ['project_id' => 'projects', 'man_power_id' => 'man_powers'],
                'users' => ['created_by'],
                'files' => [],
                'unique' => [],
            ],
            'man_power_transactions' => [
                'scope' => ['project_man_power_id', 'project_man_powers'],
                'parents' => ['project_man_power_id' => 'project_man_powers'],
                'users' => ['created_by', 'cancelled_by'],
                'files' => [],
                'unique' => [],
            ],
        ];
    }

    public static function zipPath(string $disk, string $path): string
    {
        return self::FILES_DIR.'/'.$disk.'/'.ltrim($path, '/');
    }
}
