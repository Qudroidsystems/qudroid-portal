<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry bookkeeping: a chart of accounts, journal entries whose
 * debits always equal their credits, expenses and budgets.
 *
 * Invoices, payments and paid expenses post their own entries. An entry is
 * never edited or deleted; a mistake is corrected by a reversing entry.
 */
return new class extends Migration
{
    /** The starting chart. Finance can add accounts; these system ones are used by automatic postings. */
    public const ACCOUNTS = [
        ['1000', 'Cash on hand', 'asset', true],
        ['1010', 'Bank', 'asset', true],
        ['1020', 'Paystack clearing', 'asset', true],
        ['1100', 'Accounts receivable', 'asset', true],
        ['1500', 'Equipment', 'asset', false],
        ['2000', 'Accounts payable', 'liability', false],
        ['2100', 'Customer credit', 'liability', true],
        ['2200', 'Taxes payable', 'liability', false],
        ['3000', 'Owner\'s equity', 'equity', false],
        ['3100', 'Retained earnings', 'equity', false],
        ['4000', 'Course fees', 'income', true],
        ['4100', 'Services income', 'income', true],
        ['4900', 'Other income', 'income', false],
        ['5000', 'Salaries and wages', 'expense', false],
        ['5100', 'Rent', 'expense', false],
        ['5200', 'Electricity and fuel', 'expense', false],
        ['5300', 'Internet and software', 'expense', false],
        ['5400', 'Equipment and repairs', 'expense', false],
        ['5500', 'Marketing', 'expense', false],
        ['5600', 'Transport', 'expense', false],
        ['5700', 'Bank and payment charges', 'expense', false],
        ['5800', 'Office supplies', 'expense', false],
        ['5900', 'Other expenses', 'expense', false],
    ];

    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20)->index();          // asset | liability | equity | income | expense
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false); // used by automatic postings; can't be removed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();        // QUD-JRN-2026-0001
            $table->date('date')->index();
            $table->string('description', 255);
            $table->nullableMorphs('source');              // the invoice, payment or expense that made it
            $table->string('kind', 30)->default('manual'); // manual | invoice | payment | expense | reversal
            $table->foreignId('reverses_id')->nullable()->constrained('finance_journal_entries')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('finance_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('finance_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('finance_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->index(['account_id', 'journal_entry_id']);
        });

        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();        // QUD-EXP-2026-0001
            $table->date('date');
            $table->string('payee', 150);
            $table->string('description', 500);
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();  // what it was spent on
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 20)->default('submitted')->index(); // submitted | approved | rejected | paid
            $table->string('attachment_path')->nullable(); // receipt or invoice from the supplier (private disk)
            $table->string('attachment_name')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('amount', 14, 2);
            $table->string('notes', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'starts_on']);
        });

        $now = now();
        foreach (self::ACCOUNTS as [$code, $name, $type, $system]) {
            DB::table('finance_accounts')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'type' => $type, 'is_system' => $system, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_budgets');
        Schema::dropIfExists('finance_expenses');
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journal_entries');
        Schema::dropIfExists('finance_accounts');
    }
};
