<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * More kinds of assessment, code answers and comments.
 *
 *  - kind: what students see it as (quiz, test, exam, assignment, project,
 *    coding exercise, practical, participation, oral, other). The type stays
 *    how it works: quiz (questions), assignment (students hand in work) or
 *    manual (marked in class; the instructor just enters the scores).
 *  - Assignments say what students hand in (text, code, or both, plus a
 *    file), the programming language and starter code. Quizzes get "code"
 *    questions, marked by the instructor like essays.
 *  - Comments: a conversation between the student and instructor on a piece
 *    of work, optionally pointing at a line of code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_assessments', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('type');
            $table->string('answer_format', 20)->default('text')->after('instructions');   // text | code | text_code | file
            $table->string('code_language', 20)->nullable()->after('answer_format');
            $table->text('starter_code')->nullable()->after('code_language');
            $table->boolean('allow_file')->default(true)->after('starter_code');
        });
        DB::table('academy_assessments')->update(['kind' => DB::raw('type')]);

        Schema::table('academy_questions', function (Blueprint $table) {
            $table->string('language', 20)->nullable()->after('type');
        });

        Schema::table('academy_submissions', function (Blueprint $table) {
            $table->mediumText('code')->nullable()->after('body');
            $table->string('code_language', 20)->nullable()->after('code');
        });

        Schema::create('academy_assessment_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->nullable()->constrained('academy_submissions')->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained('academy_attempts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('line')->nullable();          // a line of the code it is about
            $table->foreignId('question_id')->nullable()->constrained('academy_questions')->nullOnDelete();
            $table->timestamps();
            $table->index(['submission_id', 'id']);
            $table->index(['attempt_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_assessment_comments');
        Schema::table('academy_submissions', fn (Blueprint $t) => $t->dropColumn(['code', 'code_language']));
        Schema::table('academy_questions', fn (Blueprint $t) => $t->dropColumn('language'));
        Schema::table('academy_assessments', fn (Blueprint $t) => $t->dropColumn(['kind', 'answer_format', 'code_language', 'starter_code', 'allow_file']));
    }
};
