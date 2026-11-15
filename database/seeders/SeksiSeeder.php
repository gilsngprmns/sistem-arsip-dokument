<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeksiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            ['nama_seksi' => 'Pemerintahan', 'keterangan' => 'Mengurus administrasi pemerintahan umum', 'kode_seksi' => 'PEM'],
            ['nama_seksi' => 'Ekonomi Pembangunan', 'keterangan' => 'Mengurus urusan ekonomi dan pembangunan wilayah', 'kode_seksi' => 'EKB'],
            ['nama_seksi' => 'Kesejahteraan Rakyat', 'keterangan' => 'Mengurus urusan kesejahteraan rakyat', 'kode_seksi' => 'KSR'],
        ];

        foreach ($rows as $row) {
            DB::table('seksi')->updateOrInsert(
                ['nama_seksi' => $row['nama_seksi']],
                [
                    'kode_seksi' => $row['kode_seksi'],
                    'keterangan' => $row['keterangan'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
