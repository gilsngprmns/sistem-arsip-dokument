<?php

namespace App\Exports;

class ActivityLogExport extends BaseExcelExport
{
    public function __construct(private \Illuminate\Support\Collection $activities)
    {
    }

    public function collection()
    {
        return $this->activities;
    }

    public function headings(): array
    {
        return [
            'WAKTU',
            'PENGGUNA',
            'AKSI',
            'KETERANGAN',
            'TARGET',
            'IP ADDRESS',
        ];
    }

    public function map($log): array
    {
        $waktu = $log->created_at?->copy()->setTimezone('Asia/Jakarta')->format('d M Y, H.i.s') ?? '';

        $target = filled($log->target_type)
            ? $log->target_type.': '.($log->target_label ?? '')
            : '';

        return [
            $waktu,
            $log->user?->name ?? 'Sistem',
            $log->action,
            $log->description,
            $target,
            $log->ip_address,
        ];
    }
}
