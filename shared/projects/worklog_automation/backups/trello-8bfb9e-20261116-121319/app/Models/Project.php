<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['project_for_id', 'name'];

    public function projectFor(): BelongsTo
    {
        return $this->belongsTo(ProjectFor::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }
}
