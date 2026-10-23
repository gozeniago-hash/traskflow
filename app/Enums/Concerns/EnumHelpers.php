<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared helpers for string-backed enums: labels, dropdown options and a random case for factories.
 * An enum can define its own label() when the headline of the case name reads wrong.
 */
trait EnumHelpers
{
    public function label(): string
    {
        return Str::headline($this->name);
    }

    public static function random(): static
    {
        $cases = static::cases();

        return $cases[array_rand($cases)];
    }

    /**
     * @param array<int, string> $except values to leave out
     * @return array<int, array{id: string, name: string, value: string, label: string}>
     */
    public static function options(array $except = []): array
    {
        return collect(static::cases())
            ->reject(fn (self $case): bool => in_array($case->value, $except, true))
            ->map(fn (self $case): array => [
                'id' => $case->value,
                'name' => $case->label(),
                'value' => $case->value,
                'label' => $case->label(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, string> value => label */
    public static function getSelectDropdownData(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
