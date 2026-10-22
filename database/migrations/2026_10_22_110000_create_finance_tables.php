<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance (part 1): invoices, payments and the allocations that link them.
 *
 * Invoices and payments are separate records. One payment can settle several
 * invoices and one invoice can receive several payments; the allocation rows
 * say how much of which payment went to which invoice. An issued invoice is
 * never edited: it is voided and re-issued. A confirmed payment is never
 * edited or deleted: it is reversed, with a reason, and stays on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Gap-free numbering per document type and year (QUD-INV-2026-0001).
        Schema::create('finance_sequences', function (Blueprint $table) {
            $table->string('key', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->primary(['key', 'year']);
        });

        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();      // who is billed
            $table->foreignId('enrollment_id')->nullable()->constrained('academy_enrollments')->nullOnDelete();
            $table->string('title', 200);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);                           // sum of allocations from confirmed payments
            $table->string('status', 20)->default('issued')->index();                    // issued | partially_paid | paid | void
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('finance_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('finance_invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);                                // quantity × unit price − discount
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();                           // receipt number, given on confirmation
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();       // who paid
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('NGN');
            $table->foreignId('invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete(); // invoice it was started for (online payments)
            $table->string('method', 20);                                                 // paystack | bank_transfer | cash | pos | cheque
            $table->string('reference', 100)->nullable()->unique();                       // gateway or bank reference
            $table->string('status', 20)->default('pending')->index();                    // pending | confirmed | failed | reversed
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('finance_payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('finance_invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_allocations');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_invoice_items');
        Schema::dropIfExists('finance_invoices');
        Schema::dropIfExists('finance_sequences');
    }
};
