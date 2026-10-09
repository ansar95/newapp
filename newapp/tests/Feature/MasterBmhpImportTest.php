<?php

namespace Tests\Feature;

use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class MasterBmhpImportTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
    }

    private function csv(string $content, string $name = 'bmhp.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function previewRows(): array
    {
        return session('master_bmhp_import')['rows'];
    }

    public function test_template_can_be_downloaded(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('master-bmhps.import.template'))
            ->assertOk()
            ->assertDownload('template-import-master-bmhp.csv');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=;\n", $content);
        $this->assertStringContainsString('kode;nama;kategori;satuan;deskripsi;status', $content);
    }

    public function test_downloaded_template_can_be_imported_as_is(): void
    {
        $template = $this->actingAs($this->admin)->get(route('master-bmhps.import.template'))->streamedContent();

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => $this->csv($template)])
            ->assertRedirect(route('master-bmhps.import'))
            ->assertSessionHasNoErrors();

        $this->assertSame(['create', 'create'], array_column($this->previewRows(), 'action'));
    }

    public function test_preview_classifies_rows_without_saving(): void
    {
        $existing = $this->makeMasterBmhp($this->admin, ['code' => 'BMHP-0001', 'name' => 'Gypsum 9mm']);
        $this->makeMasterBmhp($this->admin, ['code' => 'BMHP-0002', 'name' => 'Paku Beton', 'unit' => 'Kg']);

        $content = implode("\n", [
            'kode,nama,kategori,satuan,deskripsi,status',
            ',Kabel UTP Cat6,material,Roll,Kabel 305m,',
            'BMHP-0001,Gypsum 12mm,Material,Lembar,,INACTIVE',
            'BMHP-0002,Paku Beton,Material,Kg,,',
            ',,Material,Pcs,,',
            ',Lem Kayu,Perekat,Kaleng,,',
            ',Paku Beton,Material,Kg,,',
        ]);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => $this->csv($content)])
            ->assertRedirect(route('master-bmhps.import'));

        $rows = $this->previewRows();
        $this->assertSame(['create', 'update', 'unchanged', 'error', 'error', 'create'], array_column($rows, 'action'));
        $this->assertSame([2, 3, 4, 5, 6, 7], array_column($rows, 'line'));
        $this->assertSame('Material', $rows[0]['category']);
        $this->assertSame($existing->id, $rows[1]['existing_id']);
        $this->assertSame(['Nama wajib diisi.'], $rows[3]['errors']);
        $this->assertStringContainsString('Kategori "Perekat" tidak valid', $rows[4]['errors'][0]);
        $this->assertStringContainsString('Nama sudah ada di master (BMHP-0002)', $rows[5]['warnings'][0]);

        $this->assertSame(2, MasterBmhp::query()->count());
        $this->assertSame('Gypsum 9mm', $existing->fresh()->name);

        $this->actingAs($this->admin)
            ->get(route('master-bmhps.import'))
            ->assertOk()
            ->assertSee('Preview: bmhp.csv')
            ->assertSee('Konfirmasi Import (3)')
            ->assertSee('Nama wajib diisi.');
    }

    public function test_confirm_creates_new_rows_updates_existing_and_skips_errors(): void
    {
        $existing = $this->makeMasterBmhp($this->admin, ['code' => 'BMHP-0001', 'name' => 'Gypsum 9mm']);
        $keepsStatus = $this->makeMasterBmhp($this->admin, ['code' => 'BMHP-0002', 'name' => 'Paku', 'status' => MasterBmhpStatus::Inactive->value]);

        $content = implode("\n", [
            'kode,nama,kategori,satuan,deskripsi,status',
            ',Kabel UTP Cat6,Material,Roll,Kabel 305m,',
            'BMHP-0001,Gypsum 12mm,Material,Lembar,Tebal 12mm,INACTIVE',
            'BMHP-0002,Paku Beton,Material,Kg,,',
            ',Tanpa Satuan,Material,,,',
            'SPL-01,Sarung Tangan,Consumable,Box,,nonaktif',
        ]);

        $this->actingAs($this->admin)->post(route('master-bmhps.import.preview'), ['file' => $this->csv($content)]);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.confirm'), ['token' => session('master_bmhp_import')['token']])
            ->assertRedirect(route('master-bmhps.index'))
            ->assertSessionHas('success', 'Import selesai: 2 BMHP baru, 2 diperbarui, 1 dilewati.');

        $created = MasterBmhp::query()->where('name', 'Kabel UTP Cat6')->sole();
        $this->assertSame('BMHP-0003', $created->code);
        $this->assertSame(MasterBmhpStatus::Active, $created->status);
        $this->assertSame($this->admin->id, $created->created_by);

        $custom = MasterBmhp::query()->where('code', 'SPL-01')->sole();
        $this->assertSame(MasterBmhpStatus::Inactive, $custom->status);

        $existing->refresh();
        $this->assertSame('Gypsum 12mm', $existing->name);
        $this->assertSame('Tebal 12mm', $existing->description);
        $this->assertSame(MasterBmhpStatus::Inactive, $existing->status);

        $keepsStatus->refresh();
        $this->assertSame('Paku Beton', $keepsStatus->name);
        $this->assertSame(MasterBmhpStatus::Inactive, $keepsStatus->status);

        $this->assertFalse(MasterBmhp::query()->where('name', 'Tanpa Satuan')->exists());
        $this->assertNull(session('master_bmhp_import'));
    }

    public function test_semicolon_excel_csv_with_bom_and_aliases_is_supported(): void
    {
        $content = "\xEF\xBB\xBF".implode("\r\n", [
            'Kode;Nama*;Kategori*;Satuan*;Keterangan;Status',
            ';"Cat Tembok; Putih";bahan kimia;Pail;"Isi 25 kg";Aktif',
            '',
            ';Kawat Bendrat;MATERIAL;Kg;;',
        ]);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => $this->csv($content)])
            ->assertSessionHasNoErrors();

        $rows = $this->previewRows();
        $this->assertCount(2, $rows);
        $this->assertSame('Cat Tembok; Putih', $rows[0]['name']);
        $this->assertSame('Bahan Kimia', $rows[0]['category']);
        $this->assertSame('Isi 25 kg', $rows[0]['description']);
        $this->assertSame('ACTIVE', $rows[0]['status']);
        $this->assertSame('Material', $rows[1]['category']);
        $this->assertSame([], array_merge(...array_column($rows, 'errors')));
    }

    public function test_duplicate_and_deleted_codes_are_rejected(): void
    {
        $deleted = $this->makeMasterBmhp($this->admin, ['code' => 'BMHP-0009', 'name' => 'Lama']);
        $deleted->delete();

        $content = implode("\n", [
            'kode,nama,kategori,satuan',
            'NEW-1,Barang A,Material,Pcs',
            'new-1,Barang B,Material,Pcs',
            'BMHP-0009,Barang C,Material,Pcs',
        ]);

        $this->actingAs($this->admin)->post(route('master-bmhps.import.preview'), ['file' => $this->csv($content)]);

        $rows = $this->previewRows();
        $this->assertSame(['create', 'error', 'error'], array_column($rows, 'action'));
        $this->assertSame(['Kode new-1 duplikat dengan baris 2.'], $rows[1]['errors']);
        $this->assertStringContainsString('sudah dihapus', $rows[2]['errors'][0]);
    }

    public function test_invalid_files_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => $this->csv("kode,nama\n,Barang\n")])
            ->assertSessionHasErrors(['file' => 'Kolom wajib tidak ditemukan: kategori, satuan. Gunakan template import.']);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => $this->csv("nama,kategori,satuan\n\n")])
            ->assertSessionHasErrors(['file' => 'File tidak berisi data BMHP.']);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.preview'), ['file' => UploadedFile::fake()->create('bmhp.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $this->assertNull(session('master_bmhp_import'));
    }

    public function test_confirm_requires_current_preview_token(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.confirm'), ['token' => 'tidak-ada'])
            ->assertSessionHasErrors(['business' => 'Preview import sudah kedaluwarsa. Upload ulang file CSV.']);

        $this->actingAs($this->admin)->post(route('master-bmhps.import.preview'), [
            'file' => $this->csv("nama,kategori,satuan\nBarang,Material,Pcs\n"),
        ]);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.confirm'), ['token' => 'salah'])
            ->assertSessionHasErrors('business');

        $this->assertSame(0, MasterBmhp::query()->count());
    }

    public function test_import_is_rolled_back_when_data_changed_after_preview(): void
    {
        $this->actingAs($this->admin)->post(route('master-bmhps.import.preview'), [
            'file' => $this->csv("kode,nama,kategori,satuan\nNEW-1,Barang A,Material,Pcs\nNEW-2,Barang B,Material,Pcs\n"),
        ]);

        $this->makeMasterBmhp($this->admin, ['code' => 'NEW-2', 'name' => 'Dibuat user lain']);

        $this->actingAs($this->admin)
            ->post(route('master-bmhps.import.confirm'), ['token' => session('master_bmhp_import')['token']])
            ->assertSessionHasErrors('business');

        $this->assertFalse(MasterBmhp::query()->where('code', 'NEW-1')->exists());
    }

    public function test_only_master_managers_can_import(): void
    {
        $file = $this->csv("nama,kategori,satuan\nBarang,Material,Pcs\n");

        foreach ([$this->makeApprover(), $this->makeFinance(), $this->makeProjectSite()] as $user) {
            $this->actingAs($user)->get(route('master-bmhps.import'))->assertForbidden();
            $this->actingAs($user)->get(route('master-bmhps.import.template'))->assertForbidden();
            $this->actingAs($user)->post(route('master-bmhps.import.preview'), ['file' => $file])->assertForbidden();
            $this->actingAs($user)->post(route('master-bmhps.import.confirm'), ['token' => 'x'])->assertForbidden();
        }

        $this->actingAs($this->makeApprover())
            ->get(route('master-bmhps.index'))
            ->assertOk()
            ->assertDontSee('Import CSV');

        $this->actingAs($this->makeProjectAdmin())
            ->get(route('master-bmhps.index'))
            ->assertOk()
            ->assertSee('Import CSV');

        $this->assertSame(0, MasterBmhp::query()->count());
    }
}
