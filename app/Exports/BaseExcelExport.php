<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

abstract class BaseExcelExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    public function registerEvents(): array
    {
        return [AfterSheet::class => [$this, 'afterSheet']];
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->getSheet();
        $highestRow = max(1, $sheet->getHighestRow());
        $highestCol = $sheet->getHighestColumn();

        $range = 'A1:'.$highestCol.$highestRow;

        $sheet->getStyle($range)->getAlignment()->setHorizontal('center');

        $thin = Border::BORDER_THIN;
        $sheet->getStyle('A1:'.$highestCol.'1')
            ->applyFromArray([
                'borders' => [
                    'top' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                    'left' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                    'right' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']],
                ],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF2F2F2']],
            ]);

        $sheet->getStyle('A2:'.$highestCol.$highestRow)
            ->applyFromArray([
                'borders' => [
                    'left' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                    'right' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                    'top' => ['borderStyle' => $thin, 'color' => ['argb' => 'FFCCCCCC']],
                    'bottom' => ['borderStyle' => $thin, 'color' => ['argb' => 'FFCCCCCC']],
                ],
            ]);

        $sheet->getStyle('A'.$highestRow.':'.$highestCol.$highestRow)
            ->applyFromArray([
                'borders' => [
                    'bottom' => ['borderStyle' => $thin, 'color' => ['argb' => 'FF999999']],
                ],
            ]);

        if (method_exists($this, 'afterSheetCustom')) {
            $this->afterSheetCustom($event);
        }
    }
}
