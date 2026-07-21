<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 80)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->string('source_page')->default('/contacto');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['email', 'created_at']);
        });

        $this->installContactModule();
    }

    public function down(): void
    {
        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['public_site_contacts'])
                ->pluck('id');

            DB::table('role_system_module')
                ->whereIn('system_module_id', $moduleIds)
                ->delete();
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', ['ver_contactos_sitio', 'gestionar_contactos_sitio'])
                ->pluck('id');

            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')
                ->where('slug', 'public_site_contacts')
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('slug', ['ver_contactos_sitio', 'gestionar_contactos_sitio'])
                ->delete();
        }

        Schema::dropIfExists('contact_messages');
    }

    private function installContactModule(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();

        foreach ([
            ['slug' => 'ver_contactos_sitio', 'name' => 'Ver Contactos del Sitio Web'],
            ['slug' => 'gestionar_contactos_sitio', 'name' => 'Gestionar Contactos del Sitio Web'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'public_site'],
            [
                'name' => 'Sitio web',
                'frontend_route' => null,
                'icon' => 'bx-globe',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'public_site_contacts'],
            [
                'name' => 'Contactos',
                'frontend_route' => '/admin/contactos',
                'icon' => null,
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('role_system_module')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'administrador', 'direccion'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['ver_contactos_sitio', 'gestionar_contactos_sitio'])
            ->pluck('id');

        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['public_site', 'public_site_contacts'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }

            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->updateOrInsert(
                    ['role_id' => $roleId, 'system_module_id' => $moduleId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};
