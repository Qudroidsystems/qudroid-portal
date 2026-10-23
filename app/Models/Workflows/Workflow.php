<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automation: one trigger, optional conditions, then steps run in order.
 * definition = {trigger: {key, config}, conditions: {match, rules[]}, steps: [{type, config}]}
 */
class Workflow extends Model
{
    protected $fillable = ['name', 'description', 'trigger', 'definition', 'is_active', 'created_by', 'updated_by', 'last_run_at', 'runs_count'];

    protected $casts = ['definition' => 'array', 'is_active' => 'boolean', 'last_run_at' => 'datetime'];

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): array
    {
        return array_values((array) ($this->definition['steps'] ?? []));
    }

    public function triggerConfig(): array
    {
        return (array) ($this->definition['trigger']['config'] ?? []);
    }

    public function conditions(): array
    {
        return (array) ($this->definition['conditions'] ?? ['match' => 'all', 'rules' => []]);
    }
}
