<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_schools')
            || ! Schema::hasTable('lcd_school_academic_years')
            || ! Schema::hasTable('academic_years')
            || ! Schema::hasTable('schedule_subjects')
            || ! Schema::hasTable('centro_apuntes_asignaturas')) {
            return;
        }

        $rbd = trim((string) config('libro_digital.default_school.rbd'));
        if ($rbd === '') {
            return;
        }

        $now = now();
        DB::table('lcd_schools')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'rbd' => $rbd,
            'name' => trim((string) config('libro_digital.default_school.name')) ?: 'Establecimiento institucional',
            'timezone' => trim((string) config('libro_digital.timezone')) ?: 'America/Santiago',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $school = DB::table('lcd_schools')->where('rbd', $rbd)->first(['id', 'rbd', 'timezone']);
        if (! $school) {
            return;
        }

        DB::table('academic_years')->orderBy('id')->get(['id', 'year', 'is_closed'])->each(
            fn (object $year) => DB::table('lcd_school_academic_years')->insertOrIgnore([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'rbd_snapshot' => $school->rbd,
                'year_snapshot' => $year->year,
                'timezone_snapshot' => $school->timezone ?: 'America/Santiago',
                'active' => ! (bool) $year->is_closed,
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('centro_apuntes_asignaturas')
            ->where('status', 'activa')
            ->orderBy('id')
            ->get(['name', 'code', 'area'])
            ->each(fn (object $subject) => DB::table('schedule_subjects')->insertOrIgnore([
                'name' => $subject->name,
                'code' => $subject->code,
                'color' => '#0d6efd',
                'area' => $subject->area,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        // Migración productiva forward-only: nunca elimina ni altera catálogos institucionales existentes.
    }
};
