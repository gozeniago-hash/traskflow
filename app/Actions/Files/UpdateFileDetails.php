<?php

namespace App\Actions\Files;

use App\Models\Attachment;

/** Saves the title, description, group and extra details of a file. Blank values are cleared, and extra rows without a label are dropped. */
class UpdateFileDetails
{
    /**
     * @param array<int, array{label?: ?string, value?: ?string}> $meta
     */
    public function handle(Attachment $attachment, ?string $title, ?string $description, ?string $group, array $meta): Attachment
    {
        $rows = collect($meta)
            ->map(fn (array $row): array => ['label' => trim((string) ($row['label'] ?? '')), 'value' => trim((string) ($row['value'] ?? ''))])
            ->filter(fn (array $row): bool => $row['label'] !== '')
            ->values()
            ->all();

        $attachment->update([
            'title' => self::clean($title),
            'description' => self::clean($description),
            'group' => self::clean($group),
            'meta' => $rows ?: null,
        ]);

        return $attachment;
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
