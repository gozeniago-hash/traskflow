<?php

namespace Tests\Unit\Support;

use App\Support\Timesheet;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Hours as shown in timesheets and exports. */
class TimesheetTest extends TestCase
{
    #[TestWith([0, '0.00'])]
    #[TestWith([1800, '0.50'])]
    #[TestWith([5400, '1.50'])]
    #[TestWith([3600 * 1234, '1,234.00'])]
    public function test_seconds_are_shown_as_hours_with_two_decimals(int $seconds, string $hours): void
    {
        $this->assertSame($hours, Timesheet::hours($seconds));
    }
}
