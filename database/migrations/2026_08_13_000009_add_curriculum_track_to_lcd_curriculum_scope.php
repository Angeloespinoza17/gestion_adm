<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lcd_learning_objectives')
            && ! Schema::hasColumn('lcd_learning_objectives', 'curriculum_track')) {
            Schema::table('lcd_learning_objectives', function (Blueprint $table): void {
                $table->string('curriculum_track', 30)->nullable()->after('grade_code');
                $table->index(
                    ['curriculum_catalog_id', 'grade_code', 'curriculum_track', 'active'],
                    'lcd_objectives_catalog_grade_track_idx'
                );
            });
        }

        if (Schema::hasTable('lcd_subject_curriculum_links')
            && ! Schema::hasColumn('lcd_subject_curriculum_links', 'curriculum_track')) {
            Schema::table('lcd_subject_curriculum_links', function (Blueprint $table): void {
                $table->string('curriculum_track', 30)->nullable()->after('grade_code');
                $table->index(
                    ['school_id', 'academic_year_id', 'grade_code', 'curriculum_track', 'active'],
                    'lcd_subject_links_grade_track_idx'
                );
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: el alcance curricular y su evidencia nunca se eliminan en rollback.
    }
};
