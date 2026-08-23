<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'psychology.access' => 'Acceder al módulo de Psicología',
        'psychology.referrals.create' => 'Crear derivaciones a Psicología',
        'psychology.referrals.view_own' => 'Ver derivaciones propias a Psicología',
        'psychology.referrals.view_all' => 'Ver todas las derivaciones de Psicología',
        'psychology.referrals.assign' => 'Asignar derivaciones de Psicología',
        'psychology.referrals.update' => 'Gestionar derivaciones de Psicología',
        'psychology.cases.create' => 'Abrir casos de Psicología',
        'psychology.cases.view_assigned' => 'Ver casos asignados de Psicología',
        'psychology.cases.view_all' => 'Ver todos los casos de Psicología',
        'psychology.cases.reassign' => 'Reasignar casos de Psicología',
        'psychology.sessions.create' => 'Registrar actividades de Psicología',
        'psychology.sessions.view_private' => 'Ver notas privadas de Psicología',
        'psychology.risk.create' => 'Registrar evaluaciones de riesgo',
        'psychology.risk.view' => 'Ver evaluaciones de riesgo',
        'psychology.documents.upload' => 'Subir documentos de Psicología',
        'psychology.documents.download' => 'Descargar documentos de Psicología',
        'psychology.cases.close' => 'Cerrar casos de Psicología',
        'psychology.cases.reopen' => 'Reabrir casos de Psicología',
        'psychology.reports.aggregate' => 'Ver reportes agregados de Psicología',
        'psychology.reports.nominal' => 'Ver reportes nominales de Psicología',
        'psychology.config.manage' => 'Administrar configuración de Psicología',
        'psychology.audit.view' => 'Ver auditoría de Psicología',
        'psychology.sensitive.override' => 'Acceder a contenido psicológico sensible',
    ];

    private const ORIGINAL_PSYCHOLOGIST_PERMISSIONS = [
        'psychology.access',
        'psychology.referrals.view_own',
        'psychology.referrals.update',
        'psychology.cases.create',
        'psychology.cases.view_assigned',
        'psychology.sessions.create',
        'psychology.sessions.view_private',
        'psychology.risk.create',
        'psychology.risk.view',
        'psychology.documents.upload',
        'psychology.documents.download',
        'psychology.cases.close',
        'psychology.reports.aggregate',
    ];

    private const PSYCHOLOGY_MODULES = [
        'psychology',
        'psychology_dashboard',
        'psychology_referrals',
        'psychology_cases',
        'psychology_calendar',
        'psychology_tasks',
        'psychology_alerts',
        'psychology_reports',
        'psychology_configuration',
        'psychology_audit',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'description' => 'Permiso granular del módulo confidencial de Psicología.',
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            DB::table('roles')->updateOrInsert(
                ['slug' => 'psicologo'],
                [
                    'name' => 'Psicóloga',
                    'description' => 'Profesional de Psicología Escolar con acceso integral al módulo.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('roles')->updateOrInsert(
                ['slug' => 'super_admin'],
                [
                    'name' => 'Super Admin',
                    'description' => 'Administración global del sistema.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $this->syncAccess(['super_admin'], array_keys(self::PERMISSIONS), self::PSYCHOLOGY_MODULES, $now);
            $this->syncAccess(
                ['psicologo'],
                self::ORIGINAL_PSYCHOLOGIST_PERMISSIONS,
                ['psychology', 'psychology_dashboard', 'psychology_referrals', 'psychology_cases', 'psychology_calendar', 'psychology_tasks', 'psychology_alerts', 'psychology_reports'],
                $now,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        DB::transaction(function (): void {
            $psychologyPermissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            $psychologyModuleIds = Schema::hasTable('system_modules')
                ? DB::table('system_modules')->whereIn('slug', self::PSYCHOLOGY_MODULES)->pluck('id')
                : collect();

            foreach (['psicologo', 'super_admin'] as $roleSlug) {
                $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
                if (! $roleId) {
                    continue;
                }

                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->where('role_id', $roleId)->whereIn('permission_id', $psychologyPermissionIds)->delete();
                }
                if (Schema::hasTable('role_system_module')) {
                    DB::table('role_system_module')->where('role_id', $roleId)->whereIn('system_module_id', $psychologyModuleIds)->delete();
                }
            }

            DB::table('roles')->where('slug', 'psicologo')->update([
                'name' => 'Psicólogo/a',
                'description' => 'Profesional del equipo de Psicología Escolar.',
                'updated_at' => now(),
            ]);

            $this->syncAccess(
                ['psicologo'],
                self::ORIGINAL_PSYCHOLOGIST_PERMISSIONS,
                ['psychology', 'psychology_dashboard', 'psychology_referrals', 'psychology_cases', 'psychology_calendar', 'psychology_tasks', 'psychology_alerts', 'psychology_reports'],
                now(),
            );
            $this->syncAccess(
                ['super_admin'],
                [],
                ['psychology_dashboard', 'psychology_reports', 'psychology_configuration', 'psychology_audit'],
                now(),
            );
        });
    }

    /**
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, string>  $permissionSlugs
     * @param  array<int, string>  $moduleSlugs
     */
    private function syncAccess(array $roleSlugs, array $permissionSlugs, array $moduleSlugs, mixed $now): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        $moduleIds = Schema::hasTable('system_modules')
            ? DB::table('system_modules')->whereIn('slug', $moduleSlugs)->pluck('id')
            : collect();

        foreach ($roleIds as $roleId) {
            if (Schema::hasTable('permission_role')) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('role_system_module')) {
                foreach ($moduleIds as $moduleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
