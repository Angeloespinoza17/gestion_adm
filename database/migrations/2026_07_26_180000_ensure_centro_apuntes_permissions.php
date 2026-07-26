<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        $permissions = [
            ['slug' => 'ver_modulo_centro_apuntes', 'name' => 'Ver módulo Centro de Apuntes', 'description' => 'Permite acceder al Centro de Apuntes y Pañol, su dashboard y sus secciones operativas.'],
            ['slug' => 'crear_solicitud_impresion', 'name' => 'Crear solicitud de impresión', 'description' => 'Permite crear solicitudes de impresión o producción en el Centro de Apuntes.'],
            ['slug' => 'editar_solicitud_impresion', 'name' => 'Editar solicitud de impresión', 'description' => 'Permite modificar solicitudes de impresión durante su gestión operativa.'],
            ['slug' => 'eliminar_solicitud_impresion', 'name' => 'Eliminar solicitud de impresión', 'description' => 'Permite eliminar solicitudes de impresión del Centro de Apuntes.'],
            ['slug' => 'cambiar_estado_solicitud_impresion', 'name' => 'Cambiar estado de solicitud de impresión', 'description' => 'Permite avanzar, pausar, completar o cambiar el estado de solicitudes de impresión.'],
            ['slug' => 'registrar_entrega_centro_apuntes', 'name' => 'Registrar entrega del Centro de Apuntes', 'description' => 'Permite registrar la entrega de trabajos terminados del Centro de Apuntes.'],
            ['slug' => 'administrar_asignaturas_centro_apuntes', 'name' => 'Administrar asignaturas del Centro de Apuntes', 'description' => 'Permite crear, editar y eliminar asignaturas usadas para clasificar solicitudes.'],
            ['slug' => 'administrar_maquinas_centro_apuntes', 'name' => 'Administrar máquinas del Centro de Apuntes', 'description' => 'Permite crear, editar y eliminar máquinas o equipos operativos.'],
            ['slug' => 'administrar_inventario_panol', 'name' => 'Administrar inventario del pañol', 'description' => 'Permite administrar insumos y el catálogo de inventario del pañol.'],
            ['slug' => 'registrar_movimientos_panol', 'name' => 'Registrar movimientos del pañol', 'description' => 'Permite registrar ingresos, salidas y ajustes de stock del pañol.'],
            ['slug' => 'solicitar_materiales_panol', 'name' => 'Solicitar materiales del pañol', 'description' => 'Permite crear y editar solicitudes de materiales del pañol.'],
            ['slug' => 'aprobar_entregas_panol', 'name' => 'Aprobar entregas del pañol', 'description' => 'Permite aprobar, rechazar, anular o eliminar solicitudes de materiales del pañol.'],
            ['slug' => 'registrar_entrega_materiales_panol', 'name' => 'Registrar entrega de materiales del pañol', 'description' => 'Permite confirmar el retiro y registrar la entrega física de materiales del pañol.'],
            ['slug' => 'ver_reportes_centro_apuntes', 'name' => 'Ver reportes del Centro de Apuntes', 'description' => 'Permite consultar reportes e indicadores del Centro de Apuntes y Pañol.'],
            ['slug' => 'exportar_reportes_centro_apuntes', 'name' => 'Exportar reportes del Centro de Apuntes', 'description' => 'Permite exportar reportes del Centro de Apuntes y Pañol.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                ...$permission,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')
                ->where('slug', $permission['slug'])
                ->update([
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'active' => true,
                    'updated_at' => $now,
                ]);
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'centro_apuntes',
            'name' => 'Centro de Apuntes',
            'frontend_route' => null,
            'icon' => 'bx-printer',
            'sort_order' => 86,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')
            ->where('slug', 'centro_apuntes')
            ->update([
                'name' => 'Centro de Apuntes',
                'icon' => 'bx-printer',
                'active' => true,
                'updated_at' => $now,
            ]);

        $parentId = DB::table('system_modules')->where('slug', 'centro_apuntes')->value('id');

        if (! $parentId) {
            return;
        }

        $children = [
            ['slug' => 'centro_apuntes_dashboard', 'name' => 'Dashboard', 'frontend_route' => '/centro-apuntes', 'sort_order' => 1],
            ['slug' => 'centro_apuntes_solicitudes', 'name' => 'Solicitudes y tareas', 'frontend_route' => '/centro-apuntes/solicitudes', 'sort_order' => 2],
            ['slug' => 'centro_apuntes_asignaturas', 'name' => 'Asignaturas', 'frontend_route' => '/centro-apuntes/asignaturas', 'sort_order' => 3],
            ['slug' => 'centro_apuntes_maquinas', 'name' => 'Máquinas', 'frontend_route' => '/centro-apuntes/maquinas', 'sort_order' => 4],
            ['slug' => 'centro_apuntes_insumos', 'name' => 'Pañol e insumos', 'frontend_route' => '/centro-apuntes/insumos', 'sort_order' => 5],
            ['slug' => 'centro_apuntes_movimientos', 'name' => 'Movimientos de stock', 'frontend_route' => '/centro-apuntes/movimientos', 'sort_order' => 6],
            ['slug' => 'centro_apuntes_entregas', 'name' => 'Entregas de materiales', 'frontend_route' => '/centro-apuntes/entregas', 'sort_order' => 7],
            ['slug' => 'centro_apuntes_reportes', 'name' => 'Reportes', 'frontend_route' => '/centro-apuntes/reportes', 'sort_order' => 8],
        ];

        foreach ($children as $child) {
            DB::table('system_modules')->insertOrIgnore([
                ...$child,
                'icon' => null,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')
                ->where('slug', $child['slug'])
                ->update([
                    'name' => $child['name'],
                    'frontend_route' => $child['frontend_route'],
                    'sort_order' => $child['sort_order'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->pluck('id')
            ->all();
        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', array_merge(['centro_apuntes'], array_column($children, 'slug')))
            ->pluck('id')
            ->all();

        if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            DB::table('permission_groups')->insertOrIgnore([
                'system_module_id' => $parentId,
                'name' => 'Centro de Apuntes y Pañol',
                'slug' => 'centro_apuntes_panol',
                'description' => 'Solicitudes de impresión, máquinas, asignaturas, insumos, movimientos, entregas y reportes.',
                'sort_order' => 210,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permission_groups')
                ->where('slug', 'centro_apuntes_panol')
                ->update([
                    'system_module_id' => $parentId,
                    'name' => 'Centro de Apuntes y Pañol',
                    'description' => 'Solicitudes de impresión, máquinas, asignaturas, insumos, movimientos, entregas y reportes.',
                    'sort_order' => 210,
                    'active' => true,
                    'updated_at' => $now,
                ]);

            $groupId = DB::table('permission_groups')->where('slug', 'centro_apuntes_panol')->value('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('roles')) {
            $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

            if ($superAdminRoleId && Schema::hasTable('permission_role')) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $superAdminRoleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($superAdminRoleId && Schema::hasTable('role_system_module')) {
                foreach ($moduleIds as $moduleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $superAdminRoleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Cambio aditivo: no se eliminan permisos, módulos ni asignaciones existentes.
    }
};
