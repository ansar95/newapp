<?php

namespace App\Actions\Bmhp;

class BmhpPurchaseRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function header(): array
    {
        return [
            'purchase_date' => ['required', 'date'],
            'supplier' => ['required', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'receipt_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function headerMessages(): array
    {
        return [
            'purchase_date.required' => 'Tanggal pembelian wajib diisi.',
            'supplier.required' => 'Supplier wajib diisi.',
            'receipt_file.mimes' => 'Bukti pembelian harus berupa PDF, JPG, atau PNG.',
            'receipt_file.max' => 'Ukuran bukti pembelian maksimal 5 MB.',
        ];
    }
}
