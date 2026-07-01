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
 * Report 1 — Pandangan Undang-Undang detail list to .xlsx.
 * Takes the branch-scoped + filtered query straight from LaporanKnService so
 * branch isolation is enforced in one place (the controller injects it).
 */
class PandanganUuExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['No. Permohonan', 'Nama', 'Kategori', 'Sub Kategori', 'Cawangan', 'Pandangan Undang-Undang (Ulasan Pegawai)'];
    }

    public function map($row): array
    {
        /** @var KhidmatNasihat $row */
        return CsvSafe::row([
            $row->no_permohonan,
            $row->nama_mangsa,
            $row->kategori?->jenis_kategori,
            $row->subkategori?->nama,
            $row->cawangan?->nama,
            $row->ulasan_pegawai,
        ]);
    }
}
