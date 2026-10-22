<?php

namespace App\Models\Crm;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    /** stage => [label, default probability %] */
    public const STAGES = [
        'new'         => ['New', 10],
        'contacted'   => ['Contacted', 20],
        'qualified'   => ['Qualified', 40],
        'proposal'    => ['Proposal sent', 60],
        'negotiation' => ['Negotiation', 80],
        'won'         => ['Won', 100],
        'lost'        => ['Lost', 0],
    ];
    public const OPEN = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];

    protected $table = 'crm_deals';

    protected $fillable = ['title', 'company_id', 'contact_id', 'value', 'currency', 'stage', 'probability', 'expected_close_on', 'source', 'notes',
        'owner_id', 'created_by', 'position', 'won_at', 'lost_at', 'lost_reason', 'project_id'];

    protected $casts = ['value' => 'decimal:2', 'expected_close_on' => 'date', 'won_at' => 'datetime', 'lost_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('stage', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN, true);
    }

    public function isLate(): bool
    {
        return $this->isOpen() && $this->expected_close_on && $this->expected_close_on->lt(today());
    }

    public function getStageLabelAttribute(): string
    {
        return self::STAGES[$this->stage][0] ?? ucfirst((string) $this->stage);
    }
}
