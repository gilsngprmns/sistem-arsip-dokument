<?php

namespace App\Exports;

class UserExport extends BaseExcelExport
{
    public function __construct(private \Illuminate\Support\Collection $users)
    {
    }

    public function collection()
    {
        return $this->users;
    }

    public function headings(): array
    {
        return [
            'NAMA',
            'EMAIL',
            'ROLE',
            'SEKSI',
            'STATUS',
            'DIBUAT',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            ucfirst($user->role),
            $user->seksi?->nama_seksi ?? 'Tanpa seksi',
            $user->is_active ? 'Aktif' : 'Nonaktif',
            $user->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
