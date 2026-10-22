<?php

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    /** manager: runs the project; member: works on tasks; viewer: reads and comments; client: sees what is shared with them. */
    public const ROLES = ['manager' => 'Manager', 'member' => 'Member', 'viewer' => 'Viewer', 'client' => 'Client'];

    protected $fillable = ['project_id', 'user_id', 'role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
