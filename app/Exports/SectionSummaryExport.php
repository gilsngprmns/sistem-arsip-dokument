<?php

namespace App\Exports;

class SectionSummaryExport extends BaseExcelExport
{
    public function __construct(private \Illuminate\Support\Collection $sections)
    {
    }

    public function collection()
    {
        return $this->sections;
    }

    public function headings(): array
    {
        return [
            'SEKSI',
            'JUMLAH ARSIP',
            'TERSEDIA',
            'DIPINJAM',
            'JUMLAH USER',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nama_seksi,
            $row->dokumen_arsip_count,
            $row->tersedia_count,
            $row->dipinjam_count,
            $row->users_count,
        ];
    }
}
