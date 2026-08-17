<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type', 24);
            $table->string('direct_key')->nullable()->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('only_admins_can_write')->default(false);
            $table->nullableMorphs('context');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->boolean('can_write')->default(true);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->string('notification_level', 16)->default('all');
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'pinned_at']);
            $table->index(['conversation_id', 'left_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_display_name_snapshot')->nullable();
            $table->string('kind', 16)->default('chat');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('body_format', 16)->default('plain_text');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('supersedes_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('acknowledgement_due_at')->nullable();
            $table->boolean('acknowledgement_comment_required')->default(false);
            $table->boolean('allow_replies')->default(true);
            $table->string('dispatch_status', 16)->default('sent');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('current_version')->default(1);
            $table->char('content_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'sent_at']);
            $table->index(['sender_id', 'sent_at']);
            $table->index(['requires_acknowledgement', 'acknowledgement_due_at'], 'messages_ack_due_idx');
            $table->index('dispatch_status');
        });

        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_display_name_snapshot');
            $table->string('recipient_reference_snapshot')->nullable();
            $table->boolean('acknowledgement_required')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedInteger('acknowledged_message_version')->nullable();
            $table->char('acknowledged_content_hash', 64)->nullable();
            $table->text('acknowledgement_comment')->nullable();
            $table->string('acknowledged_by_name_snapshot')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'acknowledgement_required', 'acknowledged_at'], 'recipients_user_ack_idx');
            $table->index(['message_id', 'acknowledgement_required', 'acknowledged_at'], 'recipients_message_ack_idx');
            $table->index('last_reminded_at');
        });

        Schema::create('message_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('priority', 16);
            $table->char('content_hash', 64)->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['message_id', 'version_number']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size');
            $table->char('checksum_sha256', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('message_id');
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 16);
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'reaction']);
        });

        Schema::create('messaging_audit_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['conversation_id', 'occurred_at']);
            $table->index(['message_id', 'occurred_at']);
        });

        Schema::create('messaging_temporary_uploads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size');
            $table->char('checksum_sha256', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_temporary_uploads');
        Schema::dropIfExists('messaging_audit_events');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_versions');
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
