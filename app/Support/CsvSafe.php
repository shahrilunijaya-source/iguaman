<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSV / spreadsheet formula-injection guard (INJ-03).
 *
 * A cell whose text starts with `=`, `+`, `-`, `@` (or a tab/CR) is interpreted as a formula by
 * Excel / LibreOffice / Google Sheets when the export is opened — e.g. a victim `nama` of
 * `=cmd|'/c calc'!A1` runs on the analyst's machine. Neutralize by prefixing a single quote so the
 * value is always rendered as literal text. Apply at every export/CSV render boundary that emits
 * user-controlled free text (names, IC, addresses, remarks).
 */
final class CsvSafe
{
    /** @return array<int,string> */
    public static function row(array $cells): array
    {
        return array_map(static fn ($v) => self::cell($v), $cells);
    }

    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if ($text === '') {
            return $text;
        }

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
