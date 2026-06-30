<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * W20 — generic forms-report .xlsx export driven by a column map (field => heading) and a
 * resolved row collection. Used by the queued ExportLaporanJob for bulk report exports.
 */
class LaporanExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private array $columns, private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function map($row): array
    {
        return array_map(function (string $field) use ($row) {
            $value = $row->{$field};

            return $value instanceof Carbon ? $value->format('d/m/Y') : (string) ($value ?? '');
        }, array_keys($this->columns));
    }
}
