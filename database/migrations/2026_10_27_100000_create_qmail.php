<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Qmail: mailboxes on any IMAP/SMTP server (Stalwart by default), synced into the portal.
        Schema::create('mail_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('email', 191)->unique();
            $t->string('name', 150)->nullable();
            $t->string('kind', 20)->default('personal'); // personal, shared
            $t->string('imap_host', 191);
            $t->unsignedSmallInteger('imap_port')->default(993);
            $t->string('imap_encryption', 10)->default('ssl'); // ssl, tls (STARTTLS), none
            $t->string('smtp_host', 191);
            $t->unsignedSmallInteger('smtp_port')->default(465);
            $t->string('smtp_encryption', 10)->default('ssl');
            $t->string('username', 191);
            $t->text('password');
            $t->text('signature')->nullable();
            $t->boolean('provisioned')->default(false); // created on the mail server from the portal
            $t->string('status', 20)->default('new'); // new, ok, error
            $t->string('last_error', 500)->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('mail_account_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role', 20)->default('owner'); // owner, member
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['mail_account_id', 'user_id']);
        });

        Schema::create('mail_folders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->string('path', 191);
            $t->string('name', 191);
            $t->string('role', 20)->nullable(); // inbox, sent, drafts, trash, junk, archive
            $t->string('delimiter', 4)->default('/');
            $t->unsignedBigInteger('uidvalidity')->nullable();
            $t->unsignedBigInteger('last_uid')->default(0);
            $t->unsignedInteger('unread')->default(0);
            $t->unsignedInteger('total')->default(0);
            $t->timestamps();
            $t->unique(['mail_account_id', 'path']);
        });

        Schema::create('mail_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->foreignId('mail_folder_id')->constrained('mail_folders')->cascadeOnDelete();
            $t->unsignedBigInteger('uid');
            $t->string('message_id', 191)->nullable()->index();
            $t->string('in_reply_to', 191)->nullable();
            $t->text('references')->nullable();
            $t->string('thread_key', 64);
            $t->string('subject', 500)->nullable();
            $t->string('from_name', 191)->nullable();
            $t->string('from_email', 191)->nullable();
            $t->json('to')->nullable();
            $t->json('cc')->nullable();
            $t->json('bcc')->nullable();
            $t->json('reply_to')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('snippet', 300)->nullable();
            $t->longText('body_text')->nullable();
            $t->longText('body_html')->nullable();
            $t->boolean('has_attachments')->default(false);
            $t->unsignedInteger('size')->default(0);
            $t->boolean('seen')->default(false);
            $t->boolean('flagged')->default(false);
            $t->boolean('answered')->default(false);
            $t->timestamp('snoozed_until')->nullable()->index();
            $t->timestamps();
            $t->unique(['mail_folder_id', 'uid']);
            $t->index(['mail_account_id', 'thread_key']);
            $t->index(['mail_folder_id', 'sent_at']);
        });

        Schema::create('mail_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_message_id')->constrained('mail_messages')->cascadeOnDelete();
            $t->string('filename', 255);
            $t->string('mime', 150)->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->string('content_id', 191)->nullable();
            $t->boolean('inline')->default(false);
            $t->string('path', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('mail_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('mode', 20)->default('new'); // new, reply, reply_all, forward
            $t->foreignId('source_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $t->text('to')->nullable();
            $t->text('cc')->nullable();
            $t->text('bcc')->nullable();
            $t->string('subject', 500)->nullable();
            $t->longText('body_html')->nullable();
            $t->json('attachments')->nullable();
            $t->timestamp('send_at')->nullable()->index();
            $t->string('status', 20)->default('draft'); // draft, scheduled, sending, failed
            $t->string('error', 500)->nullable();
            $t->timestamps();
        });

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $id = fn ($name) => DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            $use = $id('Use mail');
            $id('Manage mail');
            // Every staff role that can chat can also use mail (not students or clients).
            $connect = DB::table('permissions')->where('name', 'Use connect')->where('guard_name', $guard)->value('id');
            if ($connect) {
                $roles = DB::table('role_has_permissions')->where('permission_id', $connect)->pluck('role_id');
                $skip = DB::table('roles')->whereIn('name', ['Student', 'Client'])->pluck('id');
                foreach ($roles->diff($skip) as $role) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $use, 'role_id' => $role]);
                }
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        foreach (['mail_drafts', 'mail_attachments', 'mail_messages', 'mail_folders', 'mail_account_user', 'mail_accounts'] as $t) Schema::dropIfExists($t);
        DB::table('permissions')->whereIn('name', ['Use mail', 'Manage mail'])->delete();
    }
};
