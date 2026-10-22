<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace Drive: personal and project folders, files with versions,
 * sharing with people and by link. Files live on the private `local` disk
 * and are only ever served through DriveController after an access check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_folders', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->foreignId('parent_id')->nullable()->constrained('drive_folders')->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete(); // set = lives in that project's space
            $t->string('color', 9)->nullable();
            $t->timestamp('trashed_at')->nullable();
            $t->foreignId('trashed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['owner_id', 'parent_id']);
            $t->index(['project_id', 'parent_id']);
        });

        Schema::create('drive_files', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('folder_id')->nullable()->constrained('drive_folders')->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('path');                                 // current version on the local disk
            $t->string('mime', 150)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->string('description', 500)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('trashed_at')->nullable();
            $t->foreignId('trashed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['owner_id', 'folder_id']);
            $t->index(['project_id', 'folder_id']);
        });

        // Earlier versions (the current one is on drive_files).
        Schema::create('drive_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('file_id')->constrained('drive_files')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('path');
            $t->string('mime', 150)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
        });

        // A person's access to a folder (and everything in it) or a file.
        Schema::create('drive_shares', function (Blueprint $t) {
            $t->id();
            $t->string('item_type', 6);                         // folder, file
            $t->unsignedBigInteger('item_id');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 6)->default('viewer');           // viewer, editor
            $t->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['item_type', 'item_id', 'user_id']);
            $t->index('user_id');
        });

        // "Anyone with the link" — view and download only.
        Schema::create('drive_links', function (Blueprint $t) {
            $t->id();
            $t->string('item_type', 6);
            $t->unsignedBigInteger('item_id');
            $t->string('token', 64)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('downloads')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['item_type', 'item_id']);
        });

        Schema::create('drive_stars', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('item_type', 6);
            $t->unsignedBigInteger('item_id');
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_stars');
        Schema::dropIfExists('drive_links');
        Schema::dropIfExists('drive_shares');
        Schema::dropIfExists('drive_versions');
        Schema::dropIfExists('drive_files');
        Schema::dropIfExists('drive_folders');
    }
};
