<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'stage_id', 'stage_name_snapshot',
        'entered_at', 'exited_at', 'changed_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * How long the project sat here. Null while it is still the live stage.
     *
     * This is the number that tells you where work actually stalls.
     */
    public function durationInHours(): ?float
    {
        if ($this->exited_at === null) {
            return null;
        }

        return round($this->entered_at->diffInMinutes($this->exited_at) / 60, 1);
    }

    /**
     * Prefer the snapshot over the live relation.
     *
     * The stage may since have been renamed or soft-deleted; history must keep
     * showing what it actually said at the time.
     */
    public function displayName(): string
    {
        return $this->stage_name_snapshot ?: ($this->stage?->name ?? 'Unknown stage');
    }
}
