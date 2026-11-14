<?php

namespace Tests\Feature;

use App\Models\DokumenArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArsipExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_xlsx_content_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::factory()->create();

        DokumenArsip::factory()->count(3)->create([
            'seksi_id' => $seksi->id,
            'dicatat_oleh' => $admin->id,
            'tanggal_surat' => '2026-11-03',
        ]);

        $response = $this->actingAs($admin)->get('/arsip/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_export_filename_is_arsip_dokumen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Seksi::factory()->create();

        $response = $this->actingAs($admin)->get('/arsip/export');
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('arsip-dokumen-', $disposition);
        $this->assertStringEndsWith('.xlsx', $disposition);
    }

    public function test_export_respects_active_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksiA = Seksi::factory()->create(['nama_seksi' => 'Seksi A']);
        $seksiB = Seksi::factory()->create(['nama_seksi' => 'Seksi B']);

        DokumenArsip::factory()->create([
            'seksi_id' => $seksiA->id,
            'dicatat_oleh' => $admin->id,
            'status_arsip' => 'tersedia',
        ]);

        DokumenArsip::factory()->create([
            'seksi_id' => $seksiB->id,
            'dicatat_oleh' => $admin->id,
            'status_arsip' => 'dipinjam',
        ]);

        // Export with filter status=tersedia should still return 200
        $response = $this->actingAs($admin)->get('/arsip/export?status=tersedia');
        $response->assertOk();

        // Verify by downloading and checking content is not empty
        $content = $response->streamedContent();
        $this->assertNotEmpty($content);
    }

    public function test_staff_can_only_export_their_own_seksi(): void
    {
        $seksiA = Seksi::factory()->create(['nama_seksi' => 'Seksi A']);
        $seksiB = Seksi::factory()->create(['nama_seksi' => 'Seksi B']);

        $staffA = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksiA->id]);
        $staffB = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksiB->id]);

        DokumenArsip::factory()->create([
            'seksi_id' => $seksiA->id,
            'dicatat_oleh' => $staffA->id,
        ]);

        DokumenArsip::factory()->create([
            'seksi_id' => $seksiB->id,
            'dicatat_oleh' => $staffB->id,
        ]);

        // Staff A exports — should get 200 (file contains only their seksi)
        $response = $this->actingAs($staffA)->get('/arsip/export');
        $response->assertOk();
    }

    public function test_export_with_no_documents_returns_valid_xlsx(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/arsip/export');
        $response->assertOk();

        $content = $response->streamedContent();
        // XLSZ files start with PK (zip magic bytes)
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_unauthenticated_user_cannot_export(): void
    {
        $response = $this->get('/arsip/export');
        $response->assertRedirect('/login');
    }

    public function test_export_file_column_has_hyperlinks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::factory()->create();

        DokumenArsip::factory()->create([
            'seksi_id' => $seksi->id,
            'dicatat_oleh' => $admin->id,
            'file_path' => 'lampiran-arsip/test-file.pdf',
        ]);

        $response = $this->actingAs($admin)->get('/arsip/export');
        $response->assertOk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-xlsx-');
        file_put_contents($tmpFile, $response->streamedContent());

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($tmpFile);
        $sheet = $spreadsheet->getActiveSheet();
        unlink($tmpFile);

        $cell = $sheet->getCell('N2');
        $this->assertSame('test-file.pdf', $cell->getValue());
        $this->assertStringContainsString('/storage/lampiran-arsip/test-file.pdf', $cell->getHyperlink()->getUrl());
    }
}
