<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_PERMISSION = 'social_work.pickup_restrictions.manage';

    private const OLD_PERMISSION = 'gestionar_restricciones_inspectoria';

    private const MODULES = [
        ['slug' => 'social_work_dashboard', 'name' => 'Panel general', 'route' => '/social-work', 'sort' => 1],
        ['slug' => 'social_work_students', 'name' => 'Situación social', 'route' => '/social-work/students', 'sort' => 2],
        ['slug' => 'social_work_cases', 'name' => 'Casos y atenciones', 'route' => '/social-work/cases', 'sort' => 3],
        ['slug' => 'social_work_alerts', 'name' => 'Alertas', 'route' => '/social-work/alerts', 'sort' => 4],
        ['slug' => 'social_work_referrals', 'name' => 'Derivaciones', 'route' => '/social-work/referrals', 'sort' => 5],
        ['slug' => 'social_work_junaeb', 'name' => 'JUNAEB y programas', 'route' => '/social-work/junaeb', 'sort' => 6],
        ['slug' => 'social_work_health', 'name' => 'Salud estudiantil', 'route' => '/social-work/health', 'sort' => 7],
        ['slug' => 'social_work_pickup_restrictions', 'name' => 'Restricciones de retiro', 'route' => '/social-work/pickup-restrictions', 'sort' => 8],
        ['slug' => 'social_work_reports', 'name' => 'Informes', 'route' => '/social-work/reports', 'sort' => 9],
        ['slug' => 'social_work_calendar', 'name' => 'Calendario', 'route' => '/social-work/calendar', 'sort' => 10],
        ['slug' => 'social_work_settings', 'name' => 'Plantillas y protocolos', 'route' => '/social-work/settings', 'sort' => 11],
        ['slug' => 'social_work_audit', 'name' => 'Auditoría', 'route' => '/social-work/audit', 'sort' => 12],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('system_modules') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $socialWorkParentId = DB::table('system_modules')->where('slug', 'social_work')->value('id');

            if (! $socialWorkParentId) {
                $socialWorkParentId = DB::table('system_modules')->insertGetId([
                    'slug' => 'social_work',
                    'name' => 'Trabajo Social',
                    'frontend_route' => null,
                    'icon' => 'bx-heart',
                    'sort_order' => 28,
                    'active' => true,
                    'parent_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('system_modules')->where('id', $socialWorkParentId)->update([
                    'name' => 'Trabajo Social',
                    'frontend_route' => null,
                    'icon' => 'bx-heart',
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }

            $legacyRestriction = DB::table('system_modules')->where('slug', 'inspectoria_restricciones')->first();
            if ($legacyRestriction) {
                DB::table('system_modules')->where('id', $legacyRestriction->id)->update([
                    'active' => false,
                    'updated_at' => $now,
                ]);
            }

            foreach (self::MODULES as $module) {
                DB::table('system_modules')->updateOrInsert(
                    ['slug' => $module['slug']],
                    [
                        'name' => $module['name'],
                        'frontend_route' => $module['route'],
                        'icon' => null,
                        'sort_order' => $module['sort'],
                        'parent_id' => $socialWorkParentId,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            DB::table('permissions')->updateOrInsert(
                ['slug' => self::NEW_PERMISSION],
                [
                    'name' => 'Gestionar restricciones de retiro en Trabajo Social',
                    'description' => 'Permite registrar, actualizar y finalizar restricciones de retiro con alerta operativa para Portería.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            DB::table('permissions')->where('slug', self::OLD_PERMISSION)->update([
                'active' => false,
                'updated_at' => $now,
            ]);

            $socialRoleIds = DB::table('roles')->whereIn('slug', ['trabajador_social', 'super_admin'])->pluck('id');
            $newPermissionId = DB::table('permissions')->where('slug', self::NEW_PERMISSION)->value('id');
            $socialModuleIds = DB::table('system_modules')
                ->where(fn ($query) => $query->where('id', $socialWorkParentId)->orWhere('parent_id', $socialWorkParentId))
                ->pluck('id');

            foreach ($socialRoleIds as $roleId) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $newPermissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (Schema::hasTable('role_system_module')) {
                    foreach ($socialModuleIds as $moduleId) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_modules') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $inspectoriaParentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');
            $restrictionModuleId = DB::table('system_modules')->where('slug', 'social_work_pickup_restrictions')->value('id');
            $newPermissionId = DB::table('permissions')->where('slug', self::NEW_PERMISSION)->value('id');

            if ($newPermissionId && Schema::hasTable('permission_role')) {
                DB::table('permission_role')->where('permission_id', $newPermissionId)->delete();
            }
            DB::table('permissions')->where('slug', self::NEW_PERMISSION)->delete();
            DB::table('permissions')->where('slug', self::OLD_PERMISSION)->update(['active' => true, 'updated_at' => $now]);

            $newModuleSlugs = collect(self::MODULES)->pluck('slug')->reject(fn ($slug) => $slug === 'social_work_pickup_restrictions');
            $newModuleIds = DB::table('system_modules')->whereIn('slug', $newModuleSlugs)->pluck('id');
            if (Schema::hasTable('role_system_module')) {
                DB::table('role_system_module')->whereIn('system_module_id', $newModuleIds)->delete();
            }
            DB::table('system_modules')->whereIn('id', $newModuleIds)->delete();

            if ($restrictionModuleId) {
                DB::table('system_modules')->where('id', $restrictionModuleId)->update([
                    'slug' => 'inspectoria_restricciones',
                    'name' => 'Restricciones de retiro',
                    'frontend_route' => '/inspectoria/restricciones',
                    'sort_order' => 5,
                    'parent_id' => $inspectoriaParentId,
                    'updated_at' => $now,
                ]);
            }

            $oldPermissionId = DB::table('permissions')->where('slug', self::OLD_PERMISSION)->value('id');
            $inspectorRoleIds = DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->pluck('id');
            foreach ($inspectorRoleIds as $roleId) {
                if ($oldPermissionId && Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $oldPermissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
                if ($restrictionModuleId && Schema::hasTable('role_system_module')) {
                    DB::table('role_system_module')->insertOrIgnore(['role_id' => $roleId, 'system_module_id' => $restrictionModuleId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }
};
