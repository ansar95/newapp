<?php

namespace App\Actions\ManPower;

class ManPowerRules
{
    public const MAX_WORK_DAYS = 3650;

    /**
     * @return array<string, list<string>>
     */
    public static function master(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:100'],
            'daily_wage' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Nama man power wajib diisi.',
            'position.required' => 'Posisi wajib diisi.',
            'position.max' => 'Posisi maksimal 100 karakter.',
            'daily_wage.required' => 'Upah harian wajib diisi.',
            'daily_wage.numeric' => 'Upah harian harus berupa angka.',
            'daily_wage.min' => 'Upah harian tidak boleh negatif.',
            'work_days.required' => 'Jumlah hari kerja wajib diisi.',
            'work_days.integer' => 'Jumlah hari kerja harus bilangan bulat.',
            'work_days.min' => 'Jumlah hari kerja tidak boleh negatif.',
            'work_days.max' => 'Jumlah hari kerja maksimal '.self::MAX_WORK_DAYS.' hari.',
            'man_power_id.required' => 'Pilih man power.',
            'man_power_id.exists' => 'Man power tidak ditemukan.',
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.numeric' => 'Nominal harus berupa angka.',
            'amount.gt' => 'Nominal harus lebih dari 0.',
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ];
    }
}
