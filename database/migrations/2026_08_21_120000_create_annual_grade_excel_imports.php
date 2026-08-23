<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_grade_imports')) {
            Schema::create('annual_grade_imports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->unsignedSmallInteger('school_year')->index();
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('status', 40)->default('processing')->index();
                $table->string('source', 40)->default('annual_grade_excel');
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('checksum', 64);
                $table->date('source_date_from')->nullable();
                $table->date('source_date_to')->nullable();
                $table->unsignedSmallInteger('sheet_count')->default(0);
                $table->unsignedSmallInteger('course_count')->default(0);
                $table->unsignedInteger('student_rows')->default(0);
                $table->unsignedInteger('column_count')->default(0);
                $table->unsignedInteger('cell_count')->default(0);
                $table->unsignedInteger('numeric_cells')->default(0);
                $table->unsignedInteger('pending_cells')->default(0);
                $table->unsignedInteger('not_applicable_cells')->default(0);
                $table->unsignedInteger('invalid_cells')->default(0);
                $table->unsignedInteger('matched_rows')->default(0);
                $table->unsignedInteger('unmatched_rows')->default(0);
                $table->unsignedInteger('applied_results')->default(0);
                $table->unsignedInteger('preserved_manual_results')->default(0);
                $table->unsignedInteger('blocked_cells')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id', 'checksum'], 'annual_grade_import_checksum_uq');
                $table->unique(['school_id', 'academic_year_id', 'version'], 'annual_grade_import_version_uq');
                $table->index(['academic_year_id', 'status', 'created_at'], 'annual_grade_import_year_status_idx');
            });
        }

        if (! Schema::hasTable('annual_grade_import_rows')) {
            Schema::create('annual_grade_import_rows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('annual_grade_import_id')->constrained('annual_grade_imports')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
                $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
                $table->string('source_sheet', 120);
                $table->unsignedInteger('source_row');
                $table->string('source_course_name', 160);
                $table->string('normalized_course', 160)->index();
                $table->unsignedSmallInteger('list_number')->nullable();
                $table->string('source_name');
                $table->string('source_rut', 32)->nullable();
                $table->string('normalized_rut', 20)->nullable()->index();
                $table->string('match_status', 40)->default('unmatched')->index();
                $table->decimal('match_confidence', 5, 2)->nullable();
                $table->json('candidates')->nullable();
                $table->text('resolution_note')->nullable();
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('matched_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->unique(['annual_grade_import_id', 'source_sheet', 'source_row'], 'annual_grade_import_source_row_uq');
                $table->index(['annual_grade_import_id', 'match_status'], 'annual_grade_import_unmatched_idx');
                $table->index(['annual_grade_import_id', 'course_section_id', 'student_profile_id'], 'annual_grade_import_student_idx');
            });
        }

        if (! Schema::hasTable('annual_grade_import_columns')) {
            Schema::create('annual_grade_import_columns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('annual_grade_import_id')->constrained('annual_grade_imports')->restrictOnDelete();
                $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->nullOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('lcd_books')->nullOnDelete();
                $table->foreignId('teaching_group_id')->nullable()->constrained('lcd_teaching_groups')->nullOnDelete();
                $table->foreignId('teacher_assignment_id')->nullable()->constrained('lcd_teacher_assignments')->nullOnDelete();
                $table->unsignedBigInteger('assessment_id')->nullable();
                $table->string('source_sheet', 120);
                $table->unsignedInteger('source_column');
                $table->string('source_course_name', 160);
                $table->string('normalized_course', 160)->index();
                $table->string('source_subject_name');
                $table->string('normalized_subject')->index();
                $table->string('source_header', 80);
                $table->string('normalized_header', 80);
                $table->unsignedSmallInteger('header_occurrence')->default(1);
                $table->unsignedSmallInteger('subject_ordinal')->default(1);
                $table->char('import_key', 64)->nullable()->index();
                $table->string('mapping_status', 40)->default('pending')->index();
                $table->text('message')->nullable();
                $table->unsignedSmallInteger('cell_count')->default(0);
                $table->unsignedSmallInteger('numeric_count')->default(0);
                $table->unsignedSmallInteger('pending_count')->default(0);
                $table->unsignedSmallInteger('not_applicable_count')->default(0);
                $table->unsignedSmallInteger('invalid_count')->default(0);
                $table->timestamps();

                $table->unique(['annual_grade_import_id', 'source_sheet', 'source_column'], 'annual_grade_import_source_column_uq');
                $table->index(['annual_grade_import_id', 'mapping_status'], 'annual_grade_import_column_status_idx');
                $table->index(['course_section_id', 'schedule_subject_id', 'assessment_id'], 'annual_grade_import_column_scope_idx');
            });
        }

        if (! Schema::hasTable('annual_grade_import_cells')) {
            Schema::create('annual_grade_import_cells', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('annual_grade_import_id')->constrained('annual_grade_imports')->restrictOnDelete();
                $table->foreignId('annual_grade_import_row_id')->constrained('annual_grade_import_rows')->restrictOnDelete();
                $table->foreignId('annual_grade_import_column_id')->constrained('annual_grade_import_columns')->restrictOnDelete();
                $table->string('source_value', 80)->nullable();
                $table->string('value_kind', 40)->index();
                $table->decimal('numeric_value', 5, 2)->nullable();
                $table->string('apply_status', 40)->default('pending')->index();
                $table->text('message')->nullable();
                $table->unsignedBigInteger('student_result_id')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->unique(['annual_grade_import_row_id', 'annual_grade_import_column_id'], 'annual_grade_import_cell_uq');
                $table->index(['annual_grade_import_id', 'apply_status'], 'annual_grade_import_cell_status_idx');
                $table->index(['annual_grade_import_column_id', 'value_kind'], 'annual_grade_import_cell_column_kind_idx');
            });
        }

        if (Schema::hasTable('lcd_assessments') && ! Schema::hasColumn('lcd_assessments', 'annual_import_key')) {
            Schema::table('lcd_assessments', function (Blueprint $table): void {
                $table->char('annual_import_key', 64)->nullable()->after('code');
                $table->unique('annual_import_key', 'lcd_assessments_annual_import_key_uq');
            });
        }

        if (Schema::hasTable('lcd_student_results') && ! Schema::hasColumn('lcd_student_results', 'annual_grade_import_id')) {
            Schema::table('lcd_student_results', function (Blueprint $table): void {
                $table->foreignId('annual_grade_import_id')->nullable()->after('enrollment_link_id')->constrained('annual_grade_imports')->nullOnDelete();
                $table->foreignId('annual_grade_import_cell_id')->nullable()->after('annual_grade_import_id')->constrained('annual_grade_import_cells')->nullOnDelete();
                $table->index(['annual_grade_import_id', 'assessment_id'], 'lcd_student_results_annual_import_idx');
            });
        }

        $this->installPermission();
    }

    private function installPermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'importar_calificaciones',
            'name' => 'Importar calificaciones',
            'description' => 'Permite importar calificaciones anuales, conciliar estudiantes y reintentar registros pendientes.',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('slug', 'importar_calificaciones')->value('id');
        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('permission_group_permission') && Schema::hasTable('permission_groups')) {
            $groupId = DB::table('permission_groups')->where('slug', 'estudiantes')->value('id');
            if ($groupId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('permission_role') && Schema::hasTable('roles')) {
            foreach (DB::table('roles')->whereIn('slug', ['super_admin', 'administrador'])->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migración forward-only: nunca elimina notas, importaciones ni trazabilidad de producción.
    }
};
