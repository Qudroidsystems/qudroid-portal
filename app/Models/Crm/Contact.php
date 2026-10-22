<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contact extends Model
{
    protected $table = 'crm_contacts';

    protected $fillable = ['company_id', 'name', 'job_title', 'email', 'phone', 'is_primary', 'user_id', 'notes'];

    protected $casts = ['is_primary' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Their client portal account, if they have one. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }
}
