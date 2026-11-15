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
            // Kolom tersembunyi (O, P) — dipakai afterSheetCustom untuk
            // mengarahkan hyperlink ke route aman; disembunyikan di sheet.
            '',
            '',
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
            // Kolom tersembunyi O, P — id & flag enkripsi untuk hyperlink aman
            $document->id,
            $document->is_encrypted ? 1 : 0,
        ];
    }

    public function afterSheetCustom(AfterSheet $event): void
    {
        $sheet = $event->getSheet();
        $col = 'N';
        $lastRow = $sheet->getHighestDataRow();

        // Baris 2 = dokumen pertama; simpan id + is_encrypted di sel tersembunyi
        // agar hyperlink bisa mengarah ke route aman, bukan /storage/ publik.
        $colId   = 'O'; // kolom O untuk id dokumen
        $colEnc  = 'P'; // kolom P untuk flag is_encrypted
        $sheet->getColumnDimension($colId)->setVisible(false);
        $sheet->getColumnDimension($colEnc)->setVisible(false);

        for ($row = 2; $row <= $lastRow; $row++) {
            $cell = $col.$row;
            $value = $sheet->getCell($cell)->getValue();

            if (is_string($value) && $value !== '') {
                $sheet->getCell($cell)->setValue(basename($value));

                $docId  = $sheet->getCell($colId.$row)->getValue();
                $isEnc  = (bool) $sheet->getCell($colEnc.$row)->getValue();

                $url = $isEnc
                    ? route('arsip.file', ['arsip' => $docId])
                    : url('/storage/'.$value);

                $sheet->getCell($cell)->getHyperlink()
                    ->setUrl($url);

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
