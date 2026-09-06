<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_analytics_page_views')) {
            Schema::create('web_analytics_page_views', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->char('visitor_hash', 64);
                $table->char('session_hash', 64);
                $table->date('viewed_on');
                $table->string('page_type', 60)->default('page');
                $table->string('content_type', 60)->nullable();
                $table->string('content_identifier', 120)->nullable();
                $table->string('content_slug', 191)->nullable();
                $table->string('path', 512);
                $table->string('title')->nullable();
                $table->string('referrer_host', 191)->nullable();
                $table->string('traffic_source', 120)->default('Directo');
                $table->string('traffic_channel', 40)->default('direct');
                $table->string('campaign', 191)->nullable();
                $table->string('device_type', 20)->default('desktop');
                $table->string('browser_family', 40)->default('Otro');
                $table->unsignedSmallInteger('viewport_width')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('last_seen_at');
                $table->unsignedInteger('engaged_seconds')->default(0);
                $table->unsignedTinyInteger('max_scroll_depth')->default(0);
                $table->unsignedSmallInteger('interaction_count')->default(0);
                $table->boolean('is_engaged')->default(false);
                $table->timestamps();

                $table->index(['viewed_on', 'content_type'], 'web_analytics_date_content_idx');
                $table->index(['content_type', 'content_identifier'], 'web_analytics_content_idx');
                $table->index(['viewed_on', 'session_hash'], 'web_analytics_date_session_idx');
                $table->index(['viewed_on', 'visitor_hash'], 'web_analytics_date_visitor_idx');
                $table->index(['viewed_on', 'traffic_channel'], 'web_analytics_date_channel_idx');
                $table->index(['viewed_on', 'device_type'], 'web_analytics_date_device_idx');
                $table->index(['viewed_on', 'path'], 'web_analytics_date_path_idx');
            });
        }

        $this->registerAccess();
    }

    /**
     * RBAC catalog records and assignments are retained so rollback never strips access unexpectedly.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_analytics_page_views');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();

        if (! DB::table('permissions')->where('slug', 'ver_metricas_sitio')->exists()) {
            DB::table('permissions')->insert([
                'slug' => 'ver_metricas_sitio',
                'name' => 'Ver Métricas del Sitio Web',
                'description' => 'Consulta métricas anónimas de audiencia, permanencia y rendimiento del contenido público.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');

        if (! $parentId) {
            DB::table('system_modules')->insert([
                'slug' => 'public_site',
                'name' => 'Sitio web',
                'frontend_route' => null,
                'icon' => 'bx-globe',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');
        }

        if (! DB::table('system_modules')->where('slug', 'public_site_analytics')->exists()) {
            DB::table('system_modules')->insert([
                'slug' => 'public_site_analytics',
                'name' => 'Métricas web',
                'frontend_route' => '/admin/metricas-web',
                'icon' => null,
                'sort_order' => 0,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionId = DB::table('permissions')->where('slug', 'ver_metricas_sitio')->value('id');
        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['public_site', 'public_site_analytics'])
            ->pluck('id');

        if ($permissionId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            $groupId = DB::table('permission_groups')->where('slug', 'sitio_publico')->value('id');

            if ($groupId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('role_system_module')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'superadmin', 'administrador', 'direccion'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            if ($permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

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
};
