<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/** Where a task shared with the client stands on their approval. */
enum ApprovalStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting approval',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
        };
    }

    /** Tailwind classes for the status pill, kept as full strings so Tailwind can see them. */
    public function pillClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
            self::Approved => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            self::ChangesRequested => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
        };
    }
}
