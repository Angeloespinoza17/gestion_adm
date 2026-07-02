<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_schedule_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedSmallInteger('pedagogical_hour_minutes')->default(45);
            $table->decimal('default_lective_percentage', 5, 2)->default(65);
            $table->decimal('default_non_lective_percentage', 5, 2)->default(35);
            $table->string('calculation_base', 30)->default('pedagogical');
            $table->string('rounding_mode', 30)->default('nearest');
            $table->boolean('strict_validation_enabled')->default(true);
            $table->timestamps();

            $table->unique('academic_year_id');
        });

        Schema::create('school_day_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->json('days_of_week');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
            $table->index(['academic_year_id', 'active']);
        });

        Schema::create('school_day_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_day_template_id')->constrained('school_day_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('type', 40)->default('pedagogical_block');
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('order')->default(1);
            $table->boolean('assignable')->default(true);
            $table->decimal('pedagogical_hours_equivalent', 6, 2)->nullable();
            $table->timestamps();

            $table->index(['school_day_template_id', 'day_of_week', 'start_time'], 'sdb_template_day_start_idx');
            $table->index(['assignable', 'type']);
        });

        Schema::table('education_levels', function (Blueprint $table) {
            $table->foreignId('default_school_day_template_id')
                ->nullable()
                ->after('type')
                ->constrained('school_day_templates')
                ->nullOnDelete();
        });

        Schema::table('course_sections', function (Blueprint $table) {
            $table->foreignId('school_day_template_id')
                ->nullable()
                ->after('education_level_id')
                ->constrained('school_day_templates')
                ->nullOnDelete();
        });

        Schema::create('schedule_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('color', 20)->default('#0d6efd');
            $table->string('area')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index(['active', 'name']);
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['academic_year_id', 'active']);
            $table->index(['education_level_id', 'course_section_id']);
        });

        Schema::create('study_plan_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
            $table->decimal('weekly_pedagogical_hours', 6, 2)->default(0);
            $table->boolean('required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['study_plan_id', 'schedule_subject_id']);
        });

        Schema::create('teacher_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->decimal('weekly_contract_hours', 6, 2);
            $table->string('hour_type', 30)->default('chronological');
            $table->decimal('lective_percentage', 5, 2)->default(65);
            $table->decimal('non_lective_percentage', 5, 2)->default(35);
            $table->decimal('calculated_lective_hours', 6, 2)->default(0);
            $table->decimal('calculated_non_lective_hours', 6, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['staff_id', 'academic_year_id', 'active']);
        });

        Schema::create('teacher_schedule_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 50)->default('lective');
            $table->string('color', 20)->default('#0d6efd');
            $table->boolean('visible_by_default')->default(true);
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['staff_id', 'academic_year_id', 'active']);
        });

        Schema::create('schedule_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('teacher_schedule_layer_id')->constrained('teacher_schedule_layers')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->nullOnDelete();
            $table->foreignId('school_day_template_id')->nullable()->constrained('school_day_templates')->nullOnDelete();
            $table->foreignId('school_day_block_id')->nullable()->constrained('school_day_blocks')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('activity_type', 50)->default('lective_class');
            $table->decimal('pedagogical_hours', 6, 2)->default(0);
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('room_name')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'day_of_week']);
            $table->index(['staff_id', 'day_of_week', 'start_time']);
            $table->index(['course_section_id', 'day_of_week', 'start_time']);
            $table->index(['schedule_subject_id', 'course_section_id']);
            $table->index(['status', 'source']);
        });

        Schema::create('schedule_validation_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_event_id')->nullable()->constrained('schedule_events')->cascadeOnDelete();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('severity', 20)->default('warning');
            $table->string('code', 80);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'resolved']);
            $table->index(['severity', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_validation_issues');
        Schema::dropIfExists('schedule_events');
        Schema::dropIfExists('teacher_schedule_layers');
        Schema::dropIfExists('teacher_contracts');
        Schema::dropIfExists('study_plan_subjects');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('schedule_subjects');

        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_day_template_id');
        });

        Schema::table('education_levels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_school_day_template_id');
        });

        Schema::dropIfExists('school_day_blocks');
        Schema::dropIfExists('school_day_templates');
        Schema::dropIfExists('school_schedule_configs');
    }
};
