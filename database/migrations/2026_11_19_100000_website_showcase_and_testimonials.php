<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The website's student showcase and testimonials.
 *
 *  - A student's public profile (photo, headline, bio, skills, links) and their
 *    projects. Nothing is public until the student agrees and staff publish it.
 *  - Testimonials from students and from client companies. Students write
 *    theirs in the portal; clients through a link staff send them; staff can
 *    add any. Each waits for approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('academy_students')->cascadeOnDelete();
            $table->string('slug', 90)->unique();
            $table->string('headline', 120)->nullable();      // "Frontend developer", "Data analyst"
            $table->text('bio')->nullable();
            $table->string('photo', 500)->nullable();          // uploaded picture; else their portal photo
            $table->string('location', 80)->nullable();
            $table->json('skills')->nullable();
            $table->json('links')->nullable();                 // github, linkedin, portfolio, x
            $table->boolean('open_to_work')->default(false);
            $table->string('status', 12)->default('draft');    // draft | pending | published | hidden
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'featured', 'position']);
        });

        Schema::create('site_student_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('site_student_profiles')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('academy_courses')->nullOnDelete();
            $table->string('title', 150);
            $table->string('summary', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->string('url', 500)->nullable();            // live site / app / demo
            $table->string('repo_url', 500)->nullable();       // GitHub
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('site_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                         // student | client
            $table->string('name', 120);
            $table->string('role', 120)->nullable();            // "Graduate, Web Development" / "CEO"
            $table->string('organisation', 150)->nullable();    // company for clients
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable();  // 1–5
            $table->string('photo', 500)->nullable();           // face, or company logo
            $table->string('video_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->foreignId('course_id')->nullable()->constrained('academy_courses')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('academy_students')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // who wrote it, when signed in
            $table->string('source', 10)->default('staff');     // staff | student | link
            $table->string('status', 12)->default('pending');   // pending | published | hidden
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'status', 'featured', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_testimonials');
        Schema::dropIfExists('site_student_projects');
        Schema::dropIfExists('site_student_profiles');
    }
};
