<?php

namespace Database\Seeders;

use App\Models\BoksArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use Illuminate\Database\Seeder;

class RakArsipSeeder extends Seeder
{
    /**
     * Seed rak fisik sesuai pengarsipan manual:
     * tiap seksi punya rak bernomor (label: "RAK NO X SEKSI …").
     * Boks default dibuat otomatis per rak.
     */
    public function run(): void
    {
        $seksiByName = Seksi::query()
            ->get()
            ->keyBy(fn (Seksi $seksi) => $seksi->nama_seksi);

        $racks = [
            [
                'kode_rak' => 'RAK-PEM-01',
                'nama_rak' => 'Rak No. 1 Seksi Pemerintahan',
                'nomor_rak' => '1',
                'seksi' => 'Pemerintahan',
                'deskripsi' => 'Rak fisik label: RAK NO 1 SEKSI PEMERINTAHAN',
            ],
            [
                'kode_rak' => 'RAK-PEM-02',
                'nama_rak' => 'Rak No. 2 Seksi Pemerintahan',
                'nomor_rak' => '2',
                'seksi' => 'Pemerintahan',
                'deskripsi' => 'Rak tambahan Seksi Pemerintahan',
            ],
            [
                'kode_rak' => 'RAK-EKB-01',
                'nama_rak' => 'Rak No. 1 Seksi Ekonomi Pembangunan',
                'nomor_rak' => '1',
                'seksi' => 'Ekonomi Pembangunan',
                'deskripsi' => 'Rak fisik Seksi Ekonomi Pembangunan (EKOBANG)',
            ],
            [
                'kode_rak' => 'RAK-EKB-02',
                'nama_rak' => 'Rak No. 2 Seksi Ekonomi Pembangunan',
                'nomor_rak' => '2',
                'seksi' => 'Ekonomi Pembangunan',
                'deskripsi' => 'Rak tambahan Seksi Ekonomi Pembangunan',
            ],
            [
                'kode_rak' => 'RAK-KSR-01',
                'nama_rak' => 'Rak No. 1 Seksi Kesejahteraan Rakyat',
                'nomor_rak' => '1',
                'seksi' => 'Kesejahteraan Rakyat',
                'deskripsi' => 'Rak fisik label: RAK NO … SEKSI KESEJAHTERAAN RAKYAT',
            ],
            [
                'kode_rak' => 'RAK-KSR-02',
                'nama_rak' => 'Rak No. 2 Seksi Kesejahteraan Rakyat',
                'nomor_rak' => '2',
                'seksi' => 'Kesejahteraan Rakyat',
                'deskripsi' => 'Rak fisik label: RAK NO 2 SEKSI KESEJAHTERAAN RAKYAT',
            ],
        ];

        foreach ($racks as $rack) {
            $seksi = $seksiByName->get($rack['seksi']);

            $rak = RakArsip::query()->updateOrCreate(
                ['kode_rak' => $rack['kode_rak']],
                [
                    'seksi_id' => $seksi?->id,
                    'nama_rak' => $rack['nama_rak'],
                    'nomor_rak' => $rack['nomor_rak'],
                    'deskripsi' => $rack['deskripsi'],
                    'is_active' => true,
                ],
            );

            // Boks default per rak
            BoksArsip::firstOrCreate(
                ['kode_boks' => 'BOK-' . strtoupper($rack['kode_rak'])],
                [
                    'rak_id' => $rak->id,
                    'nomor_boks' => 'BOX-' . $rack['nomor_rak'],
                    'nomor_baris' => 'B-' . $rack['nomor_rak'],
                    'keterangan' => 'Boks default untuk ' . $rack['nama_rak'],
                    'is_active' => true,
                ]
            );
        }
    }
}
