<?php

namespace Tests\Unit\Support\Export;

use App\Support\Export\Csv;
use PHPUnit\Framework\TestCase;

/** Spreadsheet formulas in exported text are neutralised. */
class CsvTest extends TestCase
{
    public function test_a_row_neutralises_formula_cells_and_keeps_everything_else(): void
    {
        $row = Csv::row(['=HYPERLINK("x")', '+1+1', '@SUM(A1)', "\tTab", '-12', 42, 'Plain', '', null]);

        $this->assertSame(["'=HYPERLINK(\"x\")", "'+1+1", "'@SUM(A1)", "'\tTab", '-12', 42, 'Plain', '', null], $row);
    }
}
