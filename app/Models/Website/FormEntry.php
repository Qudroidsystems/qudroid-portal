<?php

namespace App\Models\Website;

use App\Models\Crm\Deal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormEntry extends Model
{
    protected $table = 'site_form_entries';

    protected $fillable = ['form_id', 'data', 'name', 'email', 'phone', 'status', 'deal_id', 'ip_address', 'page'];

    protected $casts = ['data' => 'array'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
