<?php

namespace App\Exports;

class RackExport extends BaseExcelExport
{
    public function __construct(private \Illuminate\Support\Collection $racks)
    {
    }

    public function collection()
    {
        return $this->racks;
    }

    public function headings(): array
    {
        return [
            'KODE RAK',
            'NAMA RAK',
            'LOKASI',
            'JUMLAH ARSIP',
            'STATUS',
        ];
    }

    public function map($row): array
    {
        return [
            $row->kode_rak,
            $row->nama_rak,
            \App\Models\RakArsip::RUANG,
            $row->dokumen_arsip_count,
            $row->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }
}
