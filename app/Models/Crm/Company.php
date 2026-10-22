<?php

namespace App\Models\Crm;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public const STATUSES = ['prospect' => 'Prospect', 'client' => 'Client', 'inactive' => 'Inactive'];

    protected $table = 'crm_companies';

    protected $fillable = ['name', 'status', 'industry', 'website', 'email', 'phone', 'address', 'city', 'country', 'source', 'notes', 'owner_id', 'created_by'];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts->first();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
