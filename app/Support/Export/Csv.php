<?php

namespace App\Support\Export;

/**
 * CSV cells that spreadsheet apps can't run as formulas.
 *
 * A task title like "=HYPERLINK(...)" or "@SUM(...)" would otherwise execute when the export
 * is opened in Excel or Google Sheets (CSV / formula injection). Such values get a leading
 * apostrophe, which spreadsheets show as plain text. Plain numbers are left alone.
 */
class Csv
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r|%]/', $value) ? "'".$value : $value;
    }

    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }
}
