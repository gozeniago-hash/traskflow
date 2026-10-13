<?php

namespace App\Livewire\Concerns;

use App\Support\Timesheet;
use Carbon\CarbonImmutable;

/** Preset date ranges ("This month", "Last week"…) plus custom from/to, in the timesheet timezone. */
trait FiltersDateRange
{
    public static function rangeLabels(): array
    {
        return [
            'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'last_week' => 'Last week',
            'last_7' => 'Last 7 days', 'this_month' => 'This month', 'last_month' => 'Last month', 'last_30' => 'Last 30 days',
            'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom range',
        ];
    }

    public function updatedRange(): void
    {
        $this->applyRange();
    }

    /** Picking a date by hand switches the preset to "Custom range". */
    public function updatedFrom(): void
    {
        $this->range = 'custom';
    }

    public function updatedTo(): void
    {
        $this->range = 'custom';
    }

    protected function applyRange(): void
    {
        $today = CarbonImmutable::now(Timesheet::tz())->startOfDay();
        [$from, $to] = match ($this->range) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()],
            'last_7' => [$today->subDays(6), $today],
            'last_30' => [$today->subDays(29), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [null, null],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };
        if ($from) {
            $this->from = $from->toDateString();
            $this->to = $to->toDateString();
        } elseif (! $this->from || ! $this->to) {
            $this->from = $today->startOfMonth()->toDateString();
            $this->to = $today->toDateString();
        }
    }
}
