<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $moduleId = DB::table('system_modules')
                ->where('slug', 'operational_staff_logbook')
                ->value('id');

            if ($moduleId) {
                DB::table('system_modules')->where('id', $moduleId)->update([
                    'name' => 'Bitácora',
                    'frontend_route' => '/bitacora',
                    'icon' => 'bx-notepad',
                    'sort_order' => 41,
                    'active' => true,
                    'parent_id' => null,
                    'updated_at' => $now,
                ]);
            }

            if (Schema::hasTable('permission_groups')) {
                DB::table('permission_groups')
                    ->where('slug', 'operational_staff_logbook')
                    ->update([
                        'name' => 'Bitácora de funcionarios',
                        'description' => 'Registro personal de funcionarios y consulta institucional protegida.',
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('permissions')) {
                DB::table('permissions')
                    ->whereIn('slug', ['operational_logbook.view', 'operational_logbook.create'])
                    ->update([
                        'description' => 'Acceso privado al módulo independiente de bitácora de funcionarios.',
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $parentId = DB::table('system_modules')
            ->where('slug', 'operational_management')
            ->value('id');

        DB::table('system_modules')
            ->where('slug', 'operational_staff_logbook')
            ->update([
                'frontend_route' => '/operational/bitacora',
                'sort_order' => 7,
                'parent_id' => $parentId,
                'updated_at' => now(),
            ]);
    }
};
