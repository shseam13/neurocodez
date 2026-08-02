<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ProjectFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'disk', 'path', 'original_name',
        'size', 'mime', 'client_visible', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'client_visible' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->where('client_visible', true);
    }

    /**
     * Always resolve through the recorded disk, never a hardcoded path.
     *
     * Files uploaded while running on Render live in R2 (its free-tier disk is
     * ephemeral and destroys uploads on every redeploy); files uploaded after a
     * move to cPanel live on local disk. Both have to keep working.
     */
    public function storage()
    {
        return Storage::disk($this->disk);
    }

    public function exists(): bool
    {
        return $this->storage()->exists($this->path);
    }

    public function download()
    {
        return $this->storage()->download($this->path, $this->original_name);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    protected static function booted(): void
    {
        // Soft-deleting keeps the row; the object is only removed on a force
        // delete so an accidental delete stays recoverable.
        static::forceDeleted(function (self $file) {
            $file->storage()->delete($file->path);
        });
    }
}
