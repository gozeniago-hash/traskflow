<?php

namespace App\Models;

use Database\Factories\DocRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocRevision extends BaseModel
{
    /** @use HasFactory<DocRevisionFactory> */
    use HasFactory;

    protected $fillable = ['doc_id', 'user_id', 'title', 'content'];

    /** @return BelongsTo<ProjectDoc, $this> */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(ProjectDoc::class, 'doc_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
