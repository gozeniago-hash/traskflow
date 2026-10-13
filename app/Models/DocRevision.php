<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocRevision extends Model
{
    protected $fillable = ['doc_id', 'user_id', 'title', 'content'];

    public function doc(): BelongsTo
    {
        return $this->belongsTo(ProjectDoc::class, 'doc_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
