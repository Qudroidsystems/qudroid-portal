<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    public $timestamps = false;

    protected $table = 'finance_journal_lines';

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'memo'];

    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
