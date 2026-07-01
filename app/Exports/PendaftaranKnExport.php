<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\KhidmatNasihat;
use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Report 6 — Pendaftaran Khidmat Nasihat detail list to .xlsx.
 * Takes the branch-scoped + filtered query from LaporanKnService. Eager-loaded
 * temuJanji avoids N+1 on the appointment-date column.
 */
class PendaftaranKnExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['No. Permohonan', 'Nama', 'No. Pengenalan', 'Umur', 'Status', 'Tarikh Temu Janji', 'Ulasan Permohonan'];
    }

    public function map($row): array
    {
        /** @var KhidmatNasihat $row */
        return CsvSafe::row([
            $row->no_permohonan,
            $row->nama_mangsa,
            $row->id_pengenalan_mangsa,
            $row->umur_mangsa,
            $row->status_kn,
            optional($row->temuJanji?->tarikh_temu_janji)->format('Y-m-d'),
            $row->ulasan_permohonan,
        ]);
    }
}
