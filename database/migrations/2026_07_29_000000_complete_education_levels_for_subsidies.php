<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('education_levels')) {
            return;
        }

        $now = now();
        $levels = [
            ['name' => 'NT1', 'order' => 1, 'type' => 'parvularia', 'teaching_code' => '10', 'grade_code' => '4'],
            ['name' => 'NT2', 'order' => 2, 'type' => 'parvularia', 'teaching_code' => '10', 'grade_code' => '5'],
            ['name' => '1° básico', 'order' => 3, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '1'],
            ['name' => '2° básico', 'order' => 4, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '2'],
            ['name' => '3° básico', 'order' => 5, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '3'],
            ['name' => '4° básico', 'order' => 6, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '4'],
            ['name' => '5° básico', 'order' => 7, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '5'],
            ['name' => '6° básico', 'order' => 8, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '6'],
            ['name' => '7° básico', 'order' => 9, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '7'],
            ['name' => '8° básico', 'order' => 10, 'type' => 'basica', 'teaching_code' => '110', 'grade_code' => '8'],
            ['name' => '1° medio', 'order' => 11, 'type' => 'media', 'teaching_code' => '310', 'grade_code' => '1'],
            ['name' => '2° medio', 'order' => 12, 'type' => 'media', 'teaching_code' => '310', 'grade_code' => '2'],
            ['name' => '3° medio', 'order' => 13, 'type' => 'media', 'teaching_code' => '310', 'grade_code' => '3'],
            ['name' => '4° medio', 'order' => 14, 'type' => 'media', 'teaching_code' => '310', 'grade_code' => '4'],
        ];

        DB::transaction(function () use ($levels, $now): void {
            foreach ($levels as $level) {
                $existing = DB::table('education_levels')->where('order', $level['order'])->first();
                $attributes = [
                    'name' => $level['name'],
                    'type' => $level['type'],
                    'updated_at' => $now,
                ];

                if ($existing) {
                    DB::table('education_levels')->where('id', $existing->id)->update($attributes);
                    $levelId = (int) $existing->id;
                } else {
                    $levelId = (int) DB::table('education_levels')->insertGetId([
                        ...$attributes,
                        'order' => $level['order'],
                        'created_at' => $now,
                    ]);
                }

                if (Schema::hasTable('accounting_subsidy_allocations')) {
                    DB::table('accounting_subsidy_allocations')
                        ->where('teaching_code', $level['teaching_code'])
                        ->where('grade_code', $level['grade_code'])
                        ->update([
                            'education_level_id' => $levelId,
                            'updated_at' => $now,
                        ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Los niveles pueden estar asociados a cursos y liquidaciones históricas.
        // Se conservan para no destruir relaciones académicas o contables.
    }
};
