<?php

namespace App\Models;

use Database\Factories\TaskFieldValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskFieldValue extends BaseModel
{
    /** @use HasFactory<TaskFieldValueFactory> */
    use HasFactory;

    protected $fillable = ['task_id', 'field_id', 'value'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<ProjectField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ProjectField::class, 'field_id');
    }
}
