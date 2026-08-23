<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SCOPED_PERMISSIONS = [
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

    private const SCOPED_MODULES = [
        'psychology',
        'psychology_dashboard',
        'psychology_referrals',
        'psychology_cases',
        'psychology_calendar',
        'psychology_tasks',
        'psychology_alerts',
        'psychology_reports',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $roleId = DB::table('roles')->where('slug', 'psicologo')->value('id');
            if (! $roleId) {
                return;
            }

            if (Schema::hasTable('permission_role')) {
                $scopedPermissionIds = DB::table('permissions')->whereIn('slug', self::SCOPED_PERMISSIONS)->pluck('id');

                foreach ($scopedPermissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('system_modules') && Schema::hasTable('role_system_module')) {
                $scopedModuleIds = DB::table('system_modules')->whereIn('slug', self::SCOPED_MODULES)->pluck('id');

                foreach ($scopedModuleIds as $moduleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Forward-only migration: production assignments are never removed.
    }
};
