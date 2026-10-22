<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A journal entry. Its debits equal its credits; it is never edited, only reversed. */
class JournalEntry extends Model
{
    public const KINDS = ['manual' => 'Manual', 'invoice' => 'Invoice', 'payment' => 'Payment', 'expense' => 'Expense', 'reversal' => 'Reversal'];

    protected $table = 'finance_journal_entries';

    protected $fillable = ['number', 'date', 'description', 'source_type', 'source_id', 'kind', 'reverses_id', 'reversed_by_id', 'created_by'];

    protected $casts = ['date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function total(): string
    {
        return number_format((float) $this->lines->sum(fn ($l) => (float) $l->debit), 2, '.', '');
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }
}
