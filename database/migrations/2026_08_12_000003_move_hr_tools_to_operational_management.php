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

        $now = now();
        DB::table('system_modules')->updateOrInsert(['slug' => 'operational_management'], [
            'name' => 'Gestión Operativa',
            'frontend_route' => null,
            'icon' => 'bx-briefcase-alt-2',
            'sort_order' => 44,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operationalId = DB::table('system_modules')->where('slug', 'operational_management')->value('id');
        $previousParentId = DB::table('system_modules')->where('slug', 'human_resources_operations')->value('id');

        DB::table('system_modules')->where('slug', 'hr_absence_management')->update([
            'parent_id' => $operationalId,
            'sort_order' => 5,
            'updated_at' => $now,
        ]);
        DB::table('system_modules')->where('slug', 'hr_recruitment_management')->update([
            'parent_id' => $operationalId,
            'sort_order' => 6,
            'updated_at' => $now,
        ]);

        if ($previousParentId && Schema::hasTable('role_system_module')) {
            $roleIds = DB::table('role_system_module')
                ->where('system_module_id', $previousParentId)
                ->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $operationalId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($previousParentId) {
            DB::table('system_modules')->where('id', $previousParentId)->update([
                'active' => false,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        DB::table('system_modules')->updateOrInsert(['slug' => 'human_resources_operations'], [
            'name' => 'Gestión de Personas',
            'frontend_route' => null,
            'icon' => 'bx-group',
            'sort_order' => 45,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentId = DB::table('system_modules')->where('slug', 'human_resources_operations')->value('id');
        DB::table('system_modules')->where('slug', 'hr_absence_management')->update(['parent_id' => $parentId, 'sort_order' => 1, 'updated_at' => $now]);
        DB::table('system_modules')->where('slug', 'hr_recruitment_management')->update(['parent_id' => $parentId, 'sort_order' => 2, 'updated_at' => $now]);
    }
};
