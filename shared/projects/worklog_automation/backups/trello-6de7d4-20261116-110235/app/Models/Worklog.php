<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worklog extends Model
{
    protected $fillable = [
        'user_id', 'project_for_id', 'project_id', 'task_id',
        'work_date', 'hours_spent', 'details',
    ];

    protected $casts = [
        'work_date'   => 'date',
        'hours_spent' => 'decimal:2',
    ];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function projectFor(): BelongsTo { return $this->belongsTo(ProjectFor::class); }
    public function project(): BelongsTo    { return $this->belongsTo(Project::class); }
    public function task(): BelongsTo       { return $this->belongsTo(Task::class); }
}
