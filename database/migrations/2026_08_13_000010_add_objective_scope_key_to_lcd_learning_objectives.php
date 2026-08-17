<?php

use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_learning_objectives')) {
            return;
        }

        if (! Schema::hasColumn('lcd_learning_objectives', 'objective_key')) {
            Schema::table('lcd_learning_objectives', function (Blueprint $table): void {
                $table->char('objective_key', 64)->nullable()->after('code');
            });
        }

        DB::table('lcd_learning_objectives as objectives')
            ->leftJoin('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->whereNull('objectives.objective_key')
            ->select(['objectives.*', 'subjects.code as subject_code'])
            ->orderBy('objectives.id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('lcd_learning_objectives')->where('id', $row->id)->update([
                        'objective_key' => CurriculumObjectiveIdentity::key([
                            'code' => $row->code,
                            'objective_type' => $row->objective_type,
                            'subject_code' => $row->subject_code,
                            'level_code' => $row->level_code,
                            'grade_code' => $row->grade_code,
                            'curriculum_track' => $row->curriculum_track ?? null,
                            'axis_code' => $row->axis_code,
                        ]),
                    ]);
                }
            }, 'objectives.id', 'id');

        if (Schema::hasIndex('lcd_learning_objectives', 'lcd_learning_objectives_catalog_code_uq')) {
            Schema::table('lcd_learning_objectives', function (Blueprint $table): void {
                $table->dropUnique('lcd_learning_objectives_catalog_code_uq');
            });
        }
        if (! Schema::hasIndex('lcd_learning_objectives', 'lcd_learning_objectives_catalog_key_uq')) {
            Schema::table('lcd_learning_objectives', function (Blueprint $table): void {
                $table->unique(
                    ['curriculum_catalog_id', 'objective_key'],
                    'lcd_learning_objectives_catalog_key_uq'
                );
                $table->index(
                    ['curriculum_catalog_id', 'code', 'objective_type'],
                    'lcd_learning_objectives_official_code_idx'
                );
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: la identidad y trazabilidad de objetivos no se eliminan en rollback.
    }
};
