<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\CsvSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * W20 - generic forms-report .xlsx export driven by a column map (field => heading) and a
 * report query. Used by the queued ExportLaporanJob for bulk report exports.
 *
 * PERF-01: FromQuery (not FromCollection) so maatwebsite chunks the query - a thousands-row
 * report is written in batches instead of materialising the whole result set in memory.
 */
class LaporanExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $columns, private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function map($row): array
    {
        return array_map(function (string $field) use ($row) {
            $value = $row->{$field};

            return $value instanceof Carbon ? $value->format('d/m/Y') : CsvSafe::cell($value);
        }, array_keys($this->columns));
    }
}
