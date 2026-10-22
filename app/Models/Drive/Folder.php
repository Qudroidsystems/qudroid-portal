<?php

namespace App\Models\Drive;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $table = 'drive_folders';

    protected $fillable = ['name', 'parent_id', 'owner_id', 'project_id', 'color', 'trashed_at', 'trashed_by'];

    protected $casts = ['trashed_at' => 'datetime'];

    public const TYPE = 'folder';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('trashed_at');
    }

    /** Root first: [grandparent, parent, this]. */
    public function trail(): array
    {
        $trail = [$this];
        $seen = [$this->id => true];
        for ($f = $this->parent; $f && !isset($seen[$f->id]); $f = $f->parent) {
            array_unshift($trail, $f);
            $seen[$f->id] = true;
        }

        return $trail;
    }
}
