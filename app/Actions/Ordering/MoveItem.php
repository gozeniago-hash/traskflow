<?php

namespace App\Actions\Ordering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Moves one item of an ordered list (sections, docs, fields, key resources) up or down
 * by one place. The list is locked while it is renumbered, so two people moving items
 * at the same time can't leave two of them in the same place.
 */
class MoveItem
{
    /**
     * @template TItem of Model
     * @template TOwner of Model
     *
     * @param HasMany<TItem, TOwner> $list the ordered relation, e.g. $project->sections()
     */
    public function handle(HasMany $list, int $id, int $direction): void
    {
        DB::transaction(function () use ($list, $id, $direction): void {
            $items = (clone $list)->lockForUpdate()->get()->values();
            $index = $items->search(fn (Model $item) => $item->getKey() === $id);
            $swap = $index === false ? null : $index + ($direction < 0 ? -1 : 1);
            if ($swap === null || ! isset($items[$swap])) {
                return;
            }

            $ordered = $items->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $i => $item) {
                $item->update(['position' => $i + 1]);
            }
        });
    }
}
