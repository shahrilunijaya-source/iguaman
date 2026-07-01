<?php

namespace App\Exports;

use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

// Filtered case-list export to .xlsx (mirrors the Senarai Kes filters).
class KesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        return Form::query()
            ->when($this->filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when($this->filters['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->when($this->filters['kategori'] ?? null, fn ($w, $v) => $w->where('kategori_kes', $v))
            ->when($this->filters['q'] ?? null, fn ($w, $v) => $w->carian($v))
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return ['No. Fail', 'Nama', 'No. KP', 'Cawangan', 'Kategori', 'Status', 'Tarikh Permohonan'];
    }

    public function map($kes): array
    {
        return [
            $kes->no_fail,
            $kes->nama,
            $kes->nokp,
            $kes->cawangan,
            $kes->kategori_kes,
            $kes->status,
            optional($kes->tarikh_permohonan)->format('Y-m-d'),
        ];
    }
}
