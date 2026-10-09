<?php

namespace Tests\Feature;

use App\Actions\Asset\CreateAsset;
use App\Actions\Asset\UpdateAsset;
use App\Enums\AssetCondition;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Support\AssetCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_can_download_asset_import_template(): void
    {
        $actor = $this->makeSuperadmin();

        $response = $this->actingAs($actor)->get(route('assets.import.template'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('code,name,category', $content);
        $this->assertStringContainsString('condition', $content);
        $this->assertStringContainsString('purchase_year', $content);
    }

    public function test_can_import_existing_and_purchase_assets_from_csv(): void
    {
        $actor = $this->makeSuperadmin();

        $csv = AssetCsv::rowsToCsv([
            AssetCsv::HEADERS,
            [
                'AST-IMP-01',
                'Monitor LG',
                'IT Equipment',
                'LG',
                '24MK',
                'Unit',
                'EXISTING',
                'GOOD',
                '2023',
                'Monitor existing',
                '',
                '',
                '',
            ],
            [
                'AST-IMP-02',
                'Drill Bosch',
                'Tools',
                'Bosch',
                'GBH',
                'Unit',
                'PURCHASE',
                'FAIR',
                '2026',
                'Drill baru',
                'SPP-IMP-01',
                '2026-02-01',
                'Permohonan drill',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('assets.csv', $csv);

        $this->actingAs($actor)
            ->post(route('assets.import'), ['file' => $file])
            ->assertRedirect();

        $existing = Asset::query()->where('code', 'AST-IMP-01')->firstOrFail();
        $this->assertSame(AssetSourceType::Existing, $existing->source_type);
        $this->assertSame(AssetStatus::Available, $existing->status);
        $this->assertSame(AssetCondition::Good, $existing->condition);
        $this->assertSame(2023, $existing->purchase_year);

        $purchase = Asset::query()->where('code', 'AST-IMP-02')->firstOrFail();
        $this->assertSame(AssetSourceType::Purchase, $purchase->source_type);
        $this->assertSame(AssetStatus::Requested, $purchase->status);
        $this->assertSame(AssetCondition::Fair, $purchase->condition);
        $this->assertSame(2026, $purchase->purchase_year);
        $this->assertSame('SPP-IMP-01', $purchase->request_number);
    }

    public function test_import_updates_existing_asset_condition_and_purchase_year(): void
    {
        $actor = $this->makeSuperadmin();

        $asset = app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-UPD-01',
            'name' => 'Printer HP',
            'category' => 'Office Equipment',
            'source_type' => AssetSourceType::Existing->value,
            'condition' => AssetCondition::Good->value,
            'purchase_year' => 2020,
        ]);

        $csv = AssetCsv::rowsToCsv([
            AssetCsv::HEADERS,
            [
                'AST-UPD-01',
                'Printer HP Updated',
                'Office Equipment',
                'HP',
                'Laser',
                'Unit',
                'EXISTING',
                'POOR',
                '2021',
                'Updated via import',
                '',
                '',
                '',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('assets-update.csv', $csv);

        $this->actingAs($actor)
            ->post(route('assets.import'), ['file' => $file])
            ->assertRedirect();

        $asset->refresh();
        $this->assertSame('Printer HP Updated', $asset->name);
        $this->assertSame(AssetCondition::Poor, $asset->condition);
        $this->assertSame(2021, $asset->purchase_year);
    }

    public function test_can_update_condition_and_purchase_year_via_form(): void
    {
        $actor = $this->makeSuperadmin();

        $asset = app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-FORM-01',
            'name' => 'Camera',
            'category' => 'Tools',
            'source_type' => AssetSourceType::Existing->value,
            'condition' => AssetCondition::Good->value,
            'purchase_year' => 2019,
        ]);

        app(UpdateAsset::class)->handle($actor, $asset, [
            'name' => 'Camera',
            'category' => 'Tools',
            'source_type' => AssetSourceType::Existing->value,
            'condition' => AssetCondition::Damaged->value,
            'purchase_year' => 2018,
            'status' => AssetStatus::Available->value,
        ]);

        $asset->refresh();
        $this->assertSame(AssetCondition::Damaged, $asset->condition);
        $this->assertSame(2018, $asset->purchase_year);

        $this->actingAs($actor)
            ->put(route('assets.update', $asset), [
                'code' => $asset->code,
                'name' => $asset->name,
                'category' => $asset->category,
                'source_type' => AssetSourceType::Existing->value,
                'condition' => AssetCondition::Fair->value,
                'purchase_year' => 2024,
                'status' => AssetStatus::Available->value,
                'unit' => 'Unit',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertSame(AssetCondition::Fair, $asset->fresh()->condition);
        $this->assertSame(2024, $asset->fresh()->purchase_year);
    }

    public function test_purchase_import_requires_request_fields(): void
    {
        $actor = $this->makeSuperadmin();

        $csv = AssetCsv::rowsToCsv([
            AssetCsv::HEADERS,
            [
                'AST-BAD-01',
                'Generator',
                'Machinery',
                'Honda',
                'X',
                'Unit',
                'PURCHASE',
                'GOOD',
                '2026',
                '',
                '',
                '',
                '',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->actingAs($actor)
            ->from(route('assets.index'))
            ->post(route('assets.import'), ['file' => $file])
            ->assertRedirect(route('assets.index'))
            ->assertSessionHasErrors('request_number');
    }

    public function test_can_export_assets_csv(): void
    {
        $actor = $this->makeSuperadmin();

        app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-EXP-01',
            'name' => 'Export Sample',
            'category' => 'Other',
            'source_type' => AssetSourceType::Existing->value,
            'condition' => AssetCondition::Good->value,
            'purchase_year' => 2022,
        ]);

        $response = $this->actingAs($actor)->get(route('assets.export'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('AST-EXP-01', $content);
        $this->assertStringContainsString('purchase_year', $content);
    }
}
