<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\LejarTuntutanBayaran;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * W15 - claim-ledger export to .xlsx. Takes the branch-scoped + filtered query
 * from LejarTuntutanService::listQuery. Eager-loaded relations avoid N+1.
 */
class LejarTuntutanExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'No. Tuntutan', 'Sumber', 'No. Fail Kes', 'Peguam (KP)', 'Jenis Tuntutan',
            'Jumlah Tuntutan', 'Jumlah Diluluskan', 'Jumlah Bayaran', 'No. Resit',
            'Status', 'Tarikh Tuntutan', 'Tarikh Bayar',
        ];
    }

    public function map($row): array
    {
        /** @var LejarTuntutanBayaran $row */
        return [
            $row->no_tuntutan,
            $row->sumber,
            $row->form?->no_fail,
            $row->kp_peguam ?? $row->peguam?->kp_peguam,
            $row->jenis_tuntutan,
            $row->jumlah_tuntutan,
            $row->jumlah_diluluskan,
            $row->jumlah_bayaran,
            $row->nombor_resit,
            $row->statusLabel(),
            optional($row->tarikh_tuntutan)->format('Y-m-d'),
            optional($row->tarikh_bayar)->format('Y-m-d'),
        ];
    }
}
