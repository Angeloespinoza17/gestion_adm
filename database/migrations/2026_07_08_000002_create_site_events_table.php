<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('category', 120)->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['featured', 'sort_order']);
        });

        $this->seedInitialEvents();
        $this->installSiteEventsModule();
    }

    public function down(): void
    {
        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['public_site_events'])
                ->pluck('id');

            DB::table('role_system_module')
                ->whereIn('system_module_id', $moduleIds)
                ->delete();
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', ['ver_eventos', 'gestionar_eventos'])
                ->pluck('id');

            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')
                ->where('slug', 'public_site_events')
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('slug', ['ver_eventos', 'gestionar_eventos'])
                ->delete();
        }

        Schema::dropIfExists('site_events');
    }

    private function seedInitialEvents(): void
    {
        $now = now();

        DB::table('site_events')->insert([
            [
                'title' => 'Sacramento de Confirmación',
                'slug' => 'sacramento-de-confirmacion',
                'summary' => 'Celebración comunitaria junto a estudiantes, familias y equipo pastoral.',
                'body' => 'Celebración comunitaria junto a estudiantes, familias y equipo pastoral.',
                'category' => 'Pastoral',
                'location' => 'Parroquia Sagrado Corazón de Jesús',
                'starts_at' => '2026-05-18 09:00:00',
                'status' => 'published',
                'featured' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Desayunos solidarios de M. Paulina',
                'slug' => 'desayunos-solidarios-de-m-paulina',
                'summary' => 'Encuentro fraterno con participación de diferentes estamentos del colegio.',
                'body' => 'Encuentro fraterno con participación de diferentes estamentos del colegio.',
                'category' => 'Comunidad',
                'location' => 'Dependencias del colegio',
                'starts_at' => '2026-05-08 09:00:00',
                'status' => 'published',
                'featured' => false,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Reunión de apoderados delegados de pastoral',
                'slug' => 'reunion-de-apoderados-delegados-de-pastoral',
                'summary' => 'Inicio del trabajo conjunto entre familias y equipo pastoral de la institución.',
                'body' => 'Inicio del trabajo conjunto entre familias y equipo pastoral de la institución.',
                'category' => 'Pastoral',
                'location' => 'Colegio Nuestra Señora del Carmen',
                'starts_at' => '2026-04-28 09:00:00',
                'status' => 'published',
                'featured' => false,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Primeras Comuniones',
                'slug' => 'primeras-comuniones',
                'summary' => 'Celebración sacramental junto a estudiantes, familias y comunidades parroquiales.',
                'body' => 'Celebración sacramental junto a estudiantes, familias y comunidades parroquiales.',
                'category' => 'Fe',
                'location' => 'Comunidad educativa pastoral',
                'starts_at' => '2026-04-21 09:00:00',
                'status' => 'published',
                'featured' => false,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function installSiteEventsModule(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();

        foreach ([
            ['slug' => 'ver_eventos', 'name' => 'Ver Eventos del Sitio Web'],
            ['slug' => 'gestionar_eventos', 'name' => 'Gestionar Eventos del Sitio Web'],
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
            ['slug' => 'public_site_events'],
            [
                'name' => 'Eventos',
                'frontend_route' => '/admin/eventos',
                'icon' => null,
                'sort_order' => 2,
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
            ->whereIn('slug', ['ver_eventos', 'gestionar_eventos'])
            ->pluck('id');

        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['public_site', 'public_site_events'])
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
