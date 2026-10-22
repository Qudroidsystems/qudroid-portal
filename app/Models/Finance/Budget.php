<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much may be spent from an expense account over a period. */
class Budget extends Model
{
    protected $table = 'finance_budgets';

    protected $fillable = ['account_id', 'starts_on', 'ends_on', 'amount', 'notes', 'created_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'amount' => 'decimal:2'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
