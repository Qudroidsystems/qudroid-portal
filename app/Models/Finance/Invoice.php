<?php

namespace App\Models\Finance;

use App\Models\Academy\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Money;

/** An invoice. Once issued it is never edited; corrections void it and issue a new one. */
class Invoice extends Model
{
    use \App\Models\Concerns\HasBusinessUnit;
    public const STATUSES = [
        'issued'         => 'Unpaid',
        'partially_paid' => 'Part paid',
        'paid'           => 'Paid',
        'void'           => 'Void',
    ];

    protected $table = 'finance_invoices';

    protected $fillable = [
        'number', 'user_id', 'enrollment_id', 'title', 'issue_date', 'due_date', 'currency',
        'subtotal', 'discount_total', 'total', 'amount_paid', 'status', 'notes', 'created_by',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'issue_date'     => 'date',
        'due_date'       => 'date',
        'voided_at'      => 'datetime',
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total'          => 'decimal:2',
        'amount_paid'    => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['issued', 'partially_paid']);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->lt(today());
    }

    /** Outstanding balance in kobo (or cents). */
    public function balanceMinor(): int
    {
        return $this->status === 'void' ? 0 : max(0, Money::minor($this->total) - Money::minor($this->amount_paid));
    }

    public function balance(): string
    {
        return Money::major($this->balanceMinor());
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::STATUSES[$this->status] ?? ucfirst((string) $this->status));
    }

    /** Student fees follow their course's unit; other invoices go to the default unit. */
    protected function defaultBusinessUnitId(): ?int
    {
        if ($this->enrollment_id) {
            return \App\Models\Academy\Enrollment::with('course')->find($this->enrollment_id)?->course?->business_unit_id
                ?? \App\Models\BusinessUnit::academyId();
        }

        return \App\Models\BusinessUnit::defaultId();
    }
}
