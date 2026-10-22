<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients & CRM: companies, their contacts, the deal pipeline and a
 * timeline of notes, calls, meetings and follow-ups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('status', 12)->default('prospect');   // prospect, client, inactive
            $t->string('industry', 100)->nullable();
            $t->string('website', 200)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address', 255)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('country', 100)->nullable();
            $t->string('source', 50)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'name']);
        });

        Schema::create('crm_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $t->string('name', 150);
            $t->string('job_title', 100)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 40)->nullable();
            $t->boolean('is_primary')->default(false);
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // client portal account
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('email');
        });

        Schema::create('crm_deals', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $t->decimal('value', 14, 2)->default(0);
            $t->string('currency', 3)->default('NGN');
            $t->string('stage', 12)->default('new');          // new, contacted, qualified, proposal, negotiation, won, lost
            $t->unsignedTinyInteger('probability')->default(10);
            $t->date('expected_close_on')->nullable();
            $t->string('source', 50)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->timestamp('won_at')->nullable();
            $t->timestamp('lost_at')->nullable();
            $t->string('lost_reason', 255)->nullable();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['stage', 'position']);
            $t->index(['owner_id', 'stage']);
        });

        Schema::create('crm_activities', function (Blueprint $t) {
            $t->id();
            $t->morphs('subject');                              // company, contact or deal
            $t->foreignId('company_id')->nullable()->constrained('crm_companies')->cascadeOnDelete();
            $t->string('type', 12)->default('note');           // note, call, email, meeting, follow_up
            $t->text('body');
            $t->timestamp('due_at')->nullable();               // follow-ups
            $t->timestamp('done_at')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['assigned_to', 'done_at', 'due_at']);
        });

        Schema::table('projects', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('client_id')->constrained('crm_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
    }
};
