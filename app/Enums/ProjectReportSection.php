<?php

namespace App\Enums;

use App\Models\User;

enum ProjectReportSection: string
{
    case Summary = 'summary';
    case Progress = 'progress';
    case Procurement = 'procurement';
    case Cost = 'cost';
    case Payment = 'payment';
    case Billing = 'billing';
    case Assets = 'assets';
    case Documents = 'documents';

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Ringkasan',
            self::Progress => 'Progress',
            self::Procurement => 'Permintaan & Pembelian',
            self::Cost => 'Biaya vs BOQ',
            self::Payment => 'Pembayaran',
            self::Billing => 'Penagihan Client',
            self::Assets => 'Aset Project',
            self::Documents => 'Dokumen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Summary => 'Posisi project saat ini: nilai kontrak, BOQ, progress, biaya aktual, dan penagihan.',
            self::Progress => 'Progress per COGS dan item BOQ (posisi saat ini).',
            self::Procurement => 'Permintaan & pembelian BMHP, Aset, dan Sewa Alat beserta status dan nilai pembayarannya.',
            self::Cost => 'Perbandingan nilai BOQ dengan biaya aktual (BMHP + Sewa Alat + Payment) per item.',
            self::Payment => 'Payment plan dan pembayaran pekerjaan yang tercatat.',
            self::Billing => 'Termin, penagihan ke client, pembayaran client, dan piutang.',
            self::Assets => 'Aset yang digunakan dan sudah dikembalikan dari project.',
            self::Documents => 'Status dokumen project dan dokumen yang masih menunggu.',
        };
    }

    /**
     * Module permission needed on top of report access. The summary only needs report access.
     */
    public function requiredPermission(): ?Permission
    {
        return match ($this) {
            self::Summary => null,
            self::Progress => Permission::ProgressView,
            self::Procurement => Permission::ProcurementView,
            self::Cost => Permission::BoqView,
            self::Payment => Permission::PaymentPlanView,
            self::Billing => Permission::BillingView,
            self::Assets => Permission::AssetView,
            self::Documents => Permission::DocumentView,
        };
    }

    /**
     * Sections whose rows are transactions with a date, so a period filter applies.
     */
    public function usesPeriod(): bool
    {
        return in_array($this, [self::Procurement, self::Payment, self::Billing, self::Assets], true);
    }

    public function isVisibleTo(User $user): bool
    {
        $permission = $this->requiredPermission();

        return $permission === null || $user->hasPermission($permission);
    }

    /**
     * @return list<self>
     */
    public static function visibleTo(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $section) => $section->isVisibleTo($user)));
    }
}
