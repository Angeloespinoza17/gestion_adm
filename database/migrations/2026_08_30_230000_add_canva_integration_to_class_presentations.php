<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canva_connections')) {
            Schema::create('canva_connections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('canva_user_id', 191);
                $table->string('canva_team_id', 191);
                $table->string('display_name')->nullable();
                $table->longText('access_token_encrypted')->nullable();
                $table->longText('refresh_token_encrypted')->nullable();
                $table->json('scopes')->nullable();
                $table->json('capabilities')->nullable();
                $table->string('status', 40)->default('active');
                $table->timestamp('access_token_expires_at');
                $table->timestamp('last_refreshed_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'user_id', 'canva_team_id'], 'canva_connections_owner_team_uq');
                $table->index(['school_id', 'user_id', 'status'], 'canva_connections_scope_idx');
            });
        }

        if (! Schema::hasTable('canva_oauth_states')) {
            Schema::create('canva_oauth_states', function (Blueprint $table): void {
                $table->id();
                $table->char('state_hash', 64)->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->text('code_verifier_encrypted');
                $table->string('redirect_to', 500)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'school_id', 'expires_at'], 'canva_oauth_states_scope_idx');
            });
        }

        if (! Schema::hasTable('class_presentations')) {
            return;
        }

        $addConnection = ! Schema::hasColumn('class_presentations', 'canva_connection_id');
        $addStatus = ! Schema::hasColumn('class_presentations', 'canva_status');
        $addAutofillJob = ! Schema::hasColumn('class_presentations', 'canva_autofill_job_id');

        Schema::table('class_presentations', function (Blueprint $table) use ($addConnection, $addStatus, $addAutofillJob): void {
            if (! Schema::hasColumn('class_presentations', 'presentation_provider')) {
                $table->string('presentation_provider', 30)->default('powerpoint')->after('progress');
            }
            if ($addConnection) {
                $table->foreignId('canva_connection_id')->nullable()->after('presentation_provider')
                    ->constrained('canva_connections')->restrictOnDelete();
            }
            if (! Schema::hasColumn('class_presentations', 'canva_brand_template_id')) {
                $table->string('canva_brand_template_id', 191)->nullable()->after('canva_connection_id');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_brand_template_title')) {
                $table->string('canva_brand_template_title')->nullable()->after('canva_brand_template_id');
            }
            if ($addStatus) {
                $table->string('canva_status', 40)->nullable()->after('canva_brand_template_title');
            }
            if ($addAutofillJob) {
                $table->string('canva_autofill_job_id', 191)->nullable()->after('canva_status');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_design_id')) {
                $table->string('canva_design_id', 191)->nullable()->after('canva_autofill_job_id');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_design_url')) {
                $table->text('canva_design_url')->nullable()->after('canva_design_id');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_edit_url')) {
                $table->text('canva_edit_url')->nullable()->after('canva_design_url');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_view_url')) {
                $table->text('canva_view_url')->nullable()->after('canva_edit_url');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_thumbnail_url')) {
                $table->text('canva_thumbnail_url')->nullable()->after('canva_view_url');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_thumbnail_expires_at')) {
                $table->timestamp('canva_thumbnail_expires_at')->nullable()->after('canva_thumbnail_url');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_urls_refreshed_at')) {
                $table->timestamp('canva_urls_refreshed_at')->nullable()->after('canva_thumbnail_expires_at');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_failure_code')) {
                $table->string('canva_failure_code', 100)->nullable()->after('canva_urls_refreshed_at');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_failure_message')) {
                $table->text('canva_failure_message')->nullable()->after('canva_failure_code');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_submitted_at')) {
                $table->timestamp('canva_submitted_at')->nullable()->after('canva_failure_message');
            }
            if (! Schema::hasColumn('class_presentations', 'canva_completed_at')) {
                $table->timestamp('canva_completed_at')->nullable()->after('canva_submitted_at');
            }

            if ($addStatus) {
                $table->index(['school_id', 'canva_status', 'updated_at'], 'class_presentations_canva_status_idx');
            }
            if ($addAutofillJob) {
                $table->unique('canva_autofill_job_id', 'class_presentations_canva_job_uq');
            }
        });
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva conexiones y diseños históricos de Canva.
    }
};
