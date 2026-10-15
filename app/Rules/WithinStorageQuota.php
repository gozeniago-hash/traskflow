<?php

namespace App\Rules;

use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Keeps a workspace's uploads under its storage allowance. */
class WithinStorageQuota implements ValidationRule
{
    public function __construct(protected ?int $workspaceId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $workspace = Workspace::find($this->workspaceId ?? auth()->user()?->current_workspace_id);
        if ($workspace && ! $workspace->hasRoomFor((int) $value->getSize())) {
            $fail($workspace->storageFullMessage());
        }
    }
}
