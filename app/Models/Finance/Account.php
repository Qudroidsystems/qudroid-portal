<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An account in the chart of accounts. */
class Account extends Model
{
    public const TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];

    /** Accounts whose balance grows with debits; the others grow with credits. */
    public const DEBIT_NORMAL = ['asset', 'expense'];

    /** Codes the automatic postings use. */
    public const CASH = '1000', BANK = '1010', PAYSTACK = '1020', RECEIVABLE = '1100', CUSTOMER_CREDIT = '2100',
        COURSE_FEES = '4000', SERVICES = '4100';

    protected $table = 'finance_accounts';

    protected $fillable = ['code', 'name', 'type', 'description', 'is_system', 'is_active'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];

    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function scopeOfType(Builder $q, string ...$types): Builder
    {
        return $q->whereIn('type', $types)->orderBy('code');
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, self::DEBIT_NORMAL, true);
    }

    /** Where money is paid from or into (cash and bank accounts). */
    public function scopeMoney(Builder $q): Builder
    {
        return $q->where('type', 'asset')->whereIn('code', [self::CASH, self::BANK, self::PAYSTACK])->orderBy('code');
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' · ' . $this->name;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
