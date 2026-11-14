<?php

namespace App\Exports;

use App\Models\DokumenArsip;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

class DocumentExport extends BaseExcelExport
{
    public function __construct(private \Illuminate\Support\Collection $documents)
    {
    }

    public function collection()
    {
        return $this->documents;
    }

    public function headings(): array
    {
        return [
            'KODE ARSIP',
            'NOMOR SURAT',
            'PERIHAL',
            'JENIS SURAT',
            'SIFAT SURAT',
            'TANGGAL SURAT',
            'TANGGAL DITERIMA',
            'ASAL INSTANSI',
            'TUJUAN INSTANSI',
            'SEKSI',
            'LOKASI RAK',
            'STATUS ARSIP',
            'CATATAN',
            'FILE DIGITAL',
        ];
    }

    public function map($document): array
    {
        return [
            $document->kode_arsip,
            $document->nomor_surat,
            $document->perihal,
            $document->jenis_surat,
            $document->sifat_surat,
            $document->tanggal_surat?->format('Y-m-d'),
            $document->tanggal_diterima?->format('Y-m-d'),
            $document->asal_instansi,
            $document->tujuan_instansi,
            $document->seksi?->nama_seksi,
            $this->rakLocation($document),
            $document->status_arsip,
            $document->catatan,
            $document->file_path,
        ];
    }

    public function afterSheetCustom(AfterSheet $event): void
    {
        $sheet = $event->getSheet();
        $col = 'N';
        $lastRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $lastRow; $row++) {
            $cell = $col.$row;
            $value = $sheet->getCell($cell)->getValue();

            if (is_string($value) && $value !== '') {
                $sheet->getCell($cell)->setValue(basename($value));

                $sheet->getCell($cell)->getHyperlink()
                    ->setUrl(url('/storage/'.$value));

                $font = $sheet->getStyle($cell)->getFont();
                $font->getColor()->setARGB('FF0000FF');
                $font->setUnderline(Font::UNDERLINE_SINGLE);
            }
        }
    }

    private function rakLocation(DokumenArsip $document): ?string
    {
        $rak = $document->rak;

        if (! $rak) {
            return null;
        }

        $boks = $document->boks?->kode_boks ? ' - '.$document->boks->kode_boks : '';

        return $rak->kode_rak.$boks;
    }
}
