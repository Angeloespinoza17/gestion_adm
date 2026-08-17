<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_books')) {
            Schema::create('lcd_books', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->string('code', 100);
                $table->string('rbd_snapshot', 20);
                $table->unsignedSmallInteger('year_snapshot');
                $table->string('level_code', 60)->nullable();
                $table->string('grade_code', 60)->nullable();
                $table->string('course_label');
                $table->string('modality_code', 60)->nullable();
                $table->string('school_day_code', 60)->nullable();
                $table->string('status', 40)->default('draft');
                $table->string('source_format', 40)->default('native');
                $table->timestamp('digital_enrollment_verified_at')->nullable();
                $table->string('previous_records_transfer_status', 40)->default('not_required');
                $table->timestamp('opened_at')->nullable();
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('retention_until')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->unsignedInteger('lock_version')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id', 'code'], 'lcd_books_school_year_code_uq');
                $table->index(['school_id', 'academic_year_id', 'status'], 'lcd_books_school_year_status_idx');
                $table->index(['course_section_id', 'status'], 'lcd_books_course_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_book_periods')) {
            Schema::create('lcd_book_periods', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->string('code', 60);
                $table->string('name');
                $table->string('type', 40)->default('term');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('status', 30)->default('open');
                $table->unsignedInteger('revision')->default(1);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['book_id', 'code'], 'lcd_book_periods_book_code_uq');
                $table->index(['book_id', 'starts_on', 'ends_on'], 'lcd_book_periods_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_teaching_groups')) {
            Schema::create('lcd_teaching_groups', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->nullOnDelete();
                $table->string('code', 100);
                $table->string('name');
                $table->string('type', 50)->default('course_subject');
                $table->string('course_snapshot');
                $table->string('subject_snapshot')->nullable();
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->string('roster_source', 50)->default('student_enrollments');
                $table->string('status', 30)->default('active');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id', 'code'], 'lcd_groups_school_year_code_uq');
                $table->index(['book_id', 'status'], 'lcd_groups_book_status_idx');
                $table->index(['course_section_id', 'schedule_subject_id'], 'lcd_groups_course_subject_idx');
            });
        }

        if (! Schema::hasTable('lcd_teacher_assignments')) {
            Schema::create('lcd_teacher_assignments', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->nullOnDelete();
                $table->string('role', 50)->default('teacher');
                $table->string('teacher_name_snapshot');
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('active')->default(true);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['teaching_group_id', 'active', 'valid_from'], 'lcd_teacher_assign_group_active_idx');
                $table->index(['staff_id', 'academic_year_id', 'active'], 'lcd_teacher_assign_staff_year_idx');
            });
        }

        if (! Schema::hasTable('lcd_enrollment_links')) {
            Schema::create('lcd_enrollment_links', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->unsignedSmallInteger('list_number')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 40)->default('active');
                $table->string('student_name_snapshot');
                $table->text('identifier_snapshot_encrypted')->nullable();
                $table->string('enrollment_status_snapshot', 60);
                $table->string('course_snapshot');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['teaching_group_id', 'student_enrollment_id'], 'lcd_enrollment_links_group_enrollment_uq');
                $table->index(['book_id', 'status'], 'lcd_enrollment_links_book_status_idx');
                $table->index(['student_profile_id', 'effective_from', 'effective_to'], 'lcd_enrollment_links_student_dates_idx');
            });
        }

        if (! Schema::hasTable('lcd_roster_snapshots')) {
            Schema::create('lcd_roster_snapshots', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->date('effective_on');
                $table->string('reason', 80)->default('scheduled_snapshot');
                $table->string('status', 30)->default('sealed');
                $table->unsignedInteger('revision')->default(1);
                $table->unsignedInteger('student_count')->default(0);
                $table->char('snapshot_hash', 64);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['teaching_group_id', 'effective_on', 'revision'], 'lcd_roster_snapshots_group_date_rev_uq');
                $table->index(['book_id', 'effective_on'], 'lcd_roster_snapshots_book_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_roster_snapshot_items')) {
            Schema::create('lcd_roster_snapshot_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('roster_snapshot_id')->constrained('lcd_roster_snapshots')->restrictOnDelete();
                $table->foreignId('enrollment_link_id')->constrained('lcd_enrollment_links')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->unsignedSmallInteger('list_number')->nullable();
                $table->date('active_from')->nullable();
                $table->date('active_to')->nullable();
                $table->string('applicability_status', 40)->default('applicable');
                $table->string('student_name_snapshot');
                $table->text('identifier_snapshot_encrypted')->nullable();
                $table->string('course_snapshot');
                $table->string('enrollment_status_snapshot', 60);
                $table->char('record_hash', 64);
                $table->timestamps();

                $table->unique(['roster_snapshot_id', 'student_profile_id'], 'lcd_roster_items_snapshot_student_uq');
                $table->index(['roster_snapshot_id', 'list_number'], 'lcd_roster_items_snapshot_list_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_catalogs')) {
            Schema::create('lcd_curriculum_catalogs', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('normative_source_id')->nullable()->constrained('lcd_normative_sources')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('name');
                $table->string('version', 50);
                $table->string('authority', 160)->nullable();
                $table->text('source_url')->nullable();
                $table->char('source_hash', 64)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['code', 'version'], 'lcd_curr_catalogs_code_version_uq');
            });
        }

        if (! Schema::hasTable('lcd_learning_objectives')) {
            Schema::create('lcd_learning_objectives', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('curriculum_catalog_id')->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->nullOnDelete();
                $table->string('level_code', 60)->nullable();
                $table->string('grade_code', 60)->nullable();
                $table->string('axis_code', 80)->nullable();
                $table->string('unit_code', 80)->nullable();
                $table->string('objective_type', 20)->default('OA');
                $table->string('code', 100);
                $table->text('description');
                $table->json('indicators')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['curriculum_catalog_id', 'code'], 'lcd_learning_objectives_catalog_code_uq');
                $table->index(['schedule_subject_id', 'grade_code', 'active'], 'lcd_learning_objectives_subject_grade_idx');
            });
        }

        if (! Schema::hasTable('lcd_subject_curriculum_links')) {
            Schema::create('lcd_subject_curriculum_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('curriculum_catalog_id')->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->string('level_code', 60)->nullable();
                $table->string('grade_code', 60)->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['school_id', 'academic_year_id', 'schedule_subject_id', 'curriculum_catalog_id'],
                    'lcd_subject_curriculum_scope_uq'
                );
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
