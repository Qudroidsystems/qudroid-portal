<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects & Tasks: projects with members, milestones, a task board,
 * comments and time logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();             // short key used in task numbers, e.g. WEB → WEB-14
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->string('client_name', 150)->nullable();
            $t->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('planning');   // planning, active, on_hold, completed, cancelled
            $t->string('priority', 10)->default('normal');   // low, normal, high, urgent
            $t->date('starts_on')->nullable();
            $t->date('due_on')->nullable();
            $t->decimal('budget', 14, 2)->nullable();
            $t->string('currency', 3)->default('NGN');
            $t->string('color', 9)->default('#0ea5e9');
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('task_seq')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'due_on']);
        });

        Schema::create('project_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10)->default('member');       // manager, member, viewer, client
            $t->timestamps();
            $t->unique(['project_id', 'user_id']);
        });

        Schema::create('project_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->date('due_on')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $t->unsignedInteger('number');
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('status', 12)->default('todo');      // todo, in_progress, review, done
            $t->string('priority', 10)->default('normal');
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('starts_on')->nullable();
            $t->date('due_on')->nullable();
            $t->unsignedInteger('estimate_minutes')->nullable();
            $t->json('checklist')->nullable();              // [{text, done}]
            $t->boolean('client_visible')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'number']);
            $t->index(['project_id', 'status', 'position']);
            $t->index(['assignee_id', 'status']);
        });

        Schema::create('project_task_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body');
            $t->boolean('is_internal')->default(false);     // hidden from clients
            $t->boolean('is_event')->default(false);        // written by the portal: "moved to Done"
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->timestamps();
        });

        Schema::create('project_time_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedInteger('minutes');
            $t->string('note', 255)->nullable();
            $t->boolean('billable')->default(true);
            $t->timestamps();
            $t->index(['project_id', 'date']);
            $t->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
        Schema::dropIfExists('project_task_comments');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
