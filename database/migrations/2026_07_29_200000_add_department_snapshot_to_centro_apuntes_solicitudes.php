<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('centro_apuntes_solicitudes')) {
            return;
        }

        Schema::table('centro_apuntes_solicitudes', function (Blueprint $table) {
            if (! Schema::hasColumn('centro_apuntes_solicitudes', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('requested_by_name_snapshot')
                    ->constrained('departments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('centro_apuntes_solicitudes', 'department_name_snapshot')) {
                $table->string('department_name_snapshot', 160)
                    ->nullable()
                    ->after('department_id');
            }
        });

        $this->backfillDepartmentSnapshots();
    }

    public function down(): void
    {
        // Los snapshots históricos se conservan intencionalmente.
    }

    private function backfillDepartmentSnapshots(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('department_staff')
            || ! Schema::hasTable('departments')
        ) {
            return;
        }

        DB::table('centro_apuntes_solicitudes')
            ->select(['id', 'requested_by_user_id'])
            ->whereNull('department_id')
            ->orderBy('id')
            ->chunkById(500, function ($requests): void {
                $userIds = $requests->pluck('requested_by_user_id')->filter()->unique()->values();
                if ($userIds->isEmpty()) {
                    return;
                }

                $staffByUser = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->whereNotNull('staff_id')
                    ->pluck('staff_id', 'id');

                if ($staffByUser->isEmpty()) {
                    return;
                }

                $departmentsByStaff = DB::table('department_staff')
                    ->join('departments', 'departments.id', '=', 'department_staff.department_id')
                    ->whereIn('department_staff.staff_id', $staffByUser->values()->unique())
                    ->where('departments.active', true)
                    ->orderBy('departments.sort_order')
                    ->orderBy('departments.name')
                    ->get([
                        'department_staff.staff_id',
                        'departments.id',
                        'departments.name',
                    ])
                    ->groupBy('staff_id')
                    ->map(fn ($departments) => $departments->first());

                foreach ($requests as $request) {
                    $staffId = $staffByUser[$request->requested_by_user_id] ?? null;
                    $department = $staffId ? ($departmentsByStaff[$staffId] ?? null) : null;

                    if (! $department) {
                        continue;
                    }

                    DB::table('centro_apuntes_solicitudes')
                        ->where('id', $request->id)
                        ->update([
                            'department_id' => $department->id,
                            'department_name_snapshot' => $department->name,
                        ]);
                }
            });
    }
};
