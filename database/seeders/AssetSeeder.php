<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::query()->first();

        if ($admin === null) {
            return;
        }

        $projectA = Project::query()->where('code', 'PRJ-001')->first();
        $projectB = Project::query()->where('code', 'PRJ-002')->first();

        $laptop = Asset::query()->updateOrCreate(
            ['code' => 'AST-0001'],
            [
                'name' => 'Laptop Dell Latitude 5450',
                'category' => 'IT Equipment',
                'brand' => 'Dell',
                'model' => 'Latitude 5450',
                'unit' => 'Unit',
                'source_type' => AssetSourceType::Purchase,
                'status' => AssetStatus::InUse,
                'condition' => AssetCondition::Good,
                'purchase_year' => 2025,
                'description' => 'Laptop engineering untuk site dan office.',
                'request_number' => 'SPP-2026-001',
                'request_date' => now()->subMonths(3)->toDateString(),
                'request_description' => 'Permohonan pembelian laptop untuk tim engineering.',
                'requested_by' => $admin->id,
                'request_status' => AssetRequestStatus::Purchased,
                'created_by' => $admin->id,
            ]
        );

        Asset::query()->updateOrCreate(
            ['code' => 'AST-0002'],
            [
                'name' => 'Printer Laser',
                'category' => 'Office Equipment',
                'brand' => 'HP',
                'model' => 'LaserJet Pro',
                'unit' => 'Unit',
                'source_type' => AssetSourceType::Existing,
                'status' => AssetStatus::Available,
                'condition' => AssetCondition::Fair,
                'purchase_year' => 2022,
                'description' => 'Printer dokumen project.',
                'request_number' => null,
                'request_date' => null,
                'request_letter_file' => null,
                'request_description' => null,
                'requested_by' => null,
                'request_status' => null,
                'created_by' => $admin->id,
            ]
        );

        Asset::query()->updateOrCreate(
            ['code' => 'AST-0003'],
            [
                'name' => 'Portable Generator',
                'category' => 'Machinery',
                'brand' => 'Honda',
                'model' => 'EU70is',
                'unit' => 'Unit',
                'source_type' => AssetSourceType::Purchase,
                'status' => AssetStatus::Requested,
                'condition' => AssetCondition::Good,
                'purchase_year' => 2026,
                'description' => 'Generator portable untuk kebutuhan site.',
                'request_number' => 'SPP-2026-003',
                'request_date' => now()->subWeek()->toDateString(),
                'request_description' => 'Permohonan pembelian generator untuk site.',
                'requested_by' => $admin->id,
                'request_status' => AssetRequestStatus::Requested,
                'created_by' => $admin->id,
            ]
        );

        Asset::query()->updateOrCreate(
            ['code' => 'AST-0004'],
            [
                'name' => 'Network Switch',
                'category' => 'IT Equipment',
                'brand' => 'Cisco',
                'model' => 'Catalyst 2960',
                'unit' => 'Unit',
                'source_type' => AssetSourceType::Existing,
                'status' => AssetStatus::Available,
                'condition' => AssetCondition::Good,
                'purchase_year' => 2023,
                'description' => 'Switch jaringan untuk temporary site network.',
                'request_number' => null,
                'request_date' => null,
                'request_letter_file' => null,
                'request_description' => null,
                'requested_by' => null,
                'request_status' => null,
                'created_by' => $admin->id,
            ]
        );

        if ($projectA === null) {
            return;
        }

        // Historical usage then current assignment on Project A.
        if ($projectB !== null) {
            ProjectAsset::query()->updateOrCreate(
                [
                    'project_id' => $projectB->id,
                    'asset_id' => $laptop->id,
                    'status' => ProjectAssetStatus::Released->value,
                ],
                [
                    'assigned_at' => now()->subMonths(4),
                    'released_at' => now()->subMonths(1),
                    'notes' => 'Riwayat penggunaan sebelumnya.',
                    'assigned_by' => $admin->id,
                    'released_by' => $admin->id,
                ]
            );
        }

        ProjectAsset::query()->updateOrCreate(
            [
                'project_id' => $projectA->id,
                'asset_id' => $laptop->id,
                'status' => ProjectAssetStatus::InUse->value,
            ],
            [
                'assigned_at' => now()->subDays(5),
                'released_at' => null,
                'notes' => 'Digunakan tim engineering.',
                'assigned_by' => $admin->id,
                'released_by' => null,
            ]
        );
    }
}
