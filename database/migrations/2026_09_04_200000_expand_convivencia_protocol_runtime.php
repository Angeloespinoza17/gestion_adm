<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration owns every object below. Failing fast prevents a later
        // rollback from deleting a similarly named object that it did not create.
        Schema::table('convivencia_protocols', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->unique()->after('id');
            $table->unsignedInteger('revision')->default(1)->after('code');
            $table->string('version_label', 80)->nullable()->after('revision');
            $table->string('regulatory_source', 191)->nullable()->after('version_label');
            $table->string('education_scope', 120)->nullable()->after('regulatory_source');
            $table->text('legal_reference')->nullable()->after('education_scope');
            $table->text('source_reference')->nullable()->after('legal_reference');
            $table->date('effective_from')->nullable()->after('source_reference');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->dateTime('published_at')->nullable()->after('effective_to');
            $table->json('metadata')->nullable()->after('published_at');
        });

        Schema::table('convivencia_protocol_steps', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->after('step_order');
            $table->longText('description')->nullable()->after('stage_name');
            $table->string('step_type', 60)->default('gestion')->after('description');
            $table->unsignedSmallInteger('deadline_value')->nullable()->after('due_days');
            $table->string('deadline_unit', 30)->default('calendar_days')->after('deadline_value');
            $table->string('deadline_anchor', 40)->default('step_started')->after('deadline_unit');
            $table->boolean('can_extend')->default(false)->after('deadline_anchor');
            $table->unsignedSmallInteger('extension_value')->nullable()->after('can_extend');
            $table->string('extension_unit', 30)->nullable()->after('extension_value');
            $table->json('completion_rule')->nullable()->after('extension_unit');
            $table->boolean('active')->default(true)->after('completion_rule');
            $table->json('metadata')->nullable()->after('active');
        });

        Schema::create('convivencia_protocol_parts', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60);
            $table->string('code', 100)->unique();
            $table->string('title', 191);
            $table->longText('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('responsible_label', 160)->nullable();
            $table->string('population_scope', 120)->nullable();
            $table->text('legal_reference')->nullable();
            $table->unsignedSmallInteger('deadline_value')->nullable();
            $table->string('deadline_unit', 30)->nullable();
            $table->string('deadline_anchor', 40)->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_sensitive')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'active'], 'conv_protocol_parts_category_active_idx');
        });

        Schema::create('convivencia_protocol_part_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained('convivencia_protocols')->cascadeOnDelete();
            $table->foreignId('protocol_step_id')->nullable()->constrained('convivencia_protocol_steps')->cascadeOnDelete();
            $table->foreignId('protocol_part_id')->constrained('convivencia_protocol_parts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_required')->default(true);
            $table->json('condition')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['protocol_id', 'protocol_step_id', 'sort_order'], 'conv_protocol_part_links_order_idx');
            $table->index(['protocol_part_id', 'protocol_id'], 'conv_protocol_part_links_part_idx');
        });

        Schema::create('convivencia_protocol_activation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_id');
            $table->foreignId('source_protocol_step_id')->nullable();
            $table->unsignedSmallInteger('step_order');
            $table->string('code', 80)->nullable();
            $table->string('stage_name', 160);
            $table->longText('description')->nullable();
            $table->string('step_type', 60)->default('gestion');
            $table->string('responsible_label', 160)->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedSmallInteger('deadline_value')->nullable();
            $table->string('deadline_unit', 30)->nullable();
            $table->string('deadline_anchor', 40)->nullable();
            $table->boolean('can_extend')->default(false);
            $table->unsignedSmallInteger('extension_value')->nullable();
            $table->string('extension_unit', 30)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('notes')->nullable();
            $table->longText('outcome')->nullable();
            $table->longText('evidence_summary')->nullable();
            $table->json('data')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['activation_id', 'step_order'], 'conv_activation_steps_order_unique');
            $table->index(['status', 'due_at'], 'conv_activation_steps_status_due_idx');
            $table->foreign('activation_id', 'conv_act_steps_activation_fk')->references('id')->on('convivencia_protocol_activations')->cascadeOnDelete();
            $table->foreign('source_protocol_step_id', 'conv_act_steps_source_fk')->references('id')->on('convivencia_protocol_steps')->nullOnDelete();
        });

        Schema::create('convivencia_protocol_activation_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_id');
            $table->foreignId('activation_step_id')->nullable();
            $table->foreignId('source_link_id')->nullable();
            $table->foreignId('protocol_part_id')->nullable();
            $table->string('category', 60);
            $table->string('code', 100);
            $table->string('title', 191);
            $table->longText('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('responsible_label', 160)->nullable();
            $table->string('population_scope', 120)->nullable();
            $table->text('legal_reference')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_evidence')->default(false);
            $table->string('status', 40)->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('notes')->nullable();
            $table->longText('evidence_summary')->nullable();
            $table->longText('outcome')->nullable();
            $table->json('data')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['activation_id', 'activation_step_id', 'sort_order'], 'conv_activation_parts_order_idx');
            $table->index(['category', 'status'], 'conv_activation_parts_category_status_idx');
            $table->index(['status', 'due_at'], 'conv_activation_parts_status_due_idx');
            $table->foreign('activation_id', 'conv_act_parts_activation_fk')->references('id')->on('convivencia_protocol_activations')->cascadeOnDelete();
            $table->foreign('activation_step_id', 'conv_act_parts_step_fk')->references('id')->on('convivencia_protocol_activation_steps')->cascadeOnDelete();
            $table->foreign('source_link_id', 'conv_act_parts_link_fk')->references('id')->on('convivencia_protocol_part_links')->nullOnDelete();
            $table->foreign('protocol_part_id', 'conv_act_parts_part_fk')->references('id')->on('convivencia_protocol_parts')->nullOnDelete();
        });

        Schema::table('convivencia_protocol_activations', function (Blueprint $table) {
            $table->foreignId('current_activation_step_id')->nullable()->after('current_step_id');
            $table->json('protocol_snapshot')->nullable()->after('involved_snapshot');
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('protocol_snapshot');
            $table->unsignedInteger('revision')->default(1)->after('progress_percentage');

            $table->foreign('current_activation_step_id', 'conv_activations_current_step_fk')
                ->references('id')->on('convivencia_protocol_activation_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        Schema::table('convivencia_protocol_activations', function (Blueprint $table) use ($isSqlite) {
            $table->dropForeign($isSqlite ? ['current_activation_step_id'] : 'conv_activations_current_step_fk');
            $table->dropColumn([
                'current_activation_step_id',
                'protocol_snapshot',
                'progress_percentage',
                'revision',
            ]);
        });

        Schema::drop('convivencia_protocol_activation_parts');
        Schema::drop('convivencia_protocol_activation_steps');
        Schema::drop('convivencia_protocol_part_links');
        Schema::drop('convivencia_protocol_parts');

        Schema::table('convivencia_protocol_steps', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'description',
                'step_type',
                'deadline_value',
                'deadline_unit',
                'deadline_anchor',
                'can_extend',
                'extension_value',
                'extension_unit',
                'completion_rule',
                'active',
                'metadata',
            ]);
        });

        Schema::table('convivencia_protocols', function (Blueprint $table) {
            $table->dropUnique('convivencia_protocols_code_unique');
            $table->dropColumn([
                'code',
                'revision',
                'version_label',
                'regulatory_source',
                'education_scope',
                'legal_reference',
                'source_reference',
                'effective_from',
                'effective_to',
                'published_at',
                'metadata',
            ]);
        });
    }
};
