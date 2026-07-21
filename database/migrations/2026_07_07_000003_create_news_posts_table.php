<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('category', 120)->nullable();
            $table->string('author_name', 120)->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->string('external_image_url', 2048)->nullable();
            $table->string('image_alt')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['featured', 'sort_order']);
        });

        $this->seedInitialNews();
        $this->installSiteNewsModule();
    }

    public function down(): void
    {
        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['public_site', 'public_site_news'])
                ->pluck('id');

            DB::table('role_system_module')
                ->whereIn('system_module_id', $moduleIds)
                ->delete();
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', ['ver_noticias', 'gestionar_noticias'])
                ->pluck('id');

            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')
                ->where('slug', 'public_site_news')
                ->delete();

            DB::table('system_modules')
                ->where('slug', 'public_site')
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('slug', ['ver_noticias', 'gestionar_noticias'])
                ->delete();
        }

        Schema::dropIfExists('news_posts');
    }

    private function seedInitialNews(): void
    {
        $now = now();

        DB::table('news_posts')->insert([
            [
                'title' => 'Sacramento de Confirmación',
                'slug' => 'sacramento-de-confirmacion',
                'excerpt' => 'Ceremonia celebrada junto a estudiantes, familias, pastoral y autoridades religiosas.',
                'body' => 'Ceremonia celebrada junto a estudiantes, familias, pastoral y autoridades religiosas.',
                'category' => 'Pastoral',
                'author_name' => 'Colegio Nuestra Señora del Carmen',
                'external_image_url' => 'niceschool/assets/img/blog/blog-post-1.webp',
                'image_alt' => 'Sacramento de Confirmación',
                'status' => 'published',
                'featured' => true,
                'sort_order' => 1,
                'published_at' => '2026-05-18 09:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Destacada participación deportiva',
                'slug' => 'destacada-participacion-deportiva',
                'excerpt' => 'Estudiantes representan al colegio con entusiasmo, disciplina y dedicación.',
                'body' => 'Estudiantes representan al colegio con entusiasmo, disciplina y dedicación.',
                'category' => 'Deportes',
                'author_name' => 'Colegio Nuestra Señora del Carmen',
                'external_image_url' => 'niceschool/assets/img/blog/blog-post-2.webp',
                'image_alt' => 'Gimnasia rítmica',
                'status' => 'published',
                'featured' => true,
                'sort_order' => 2,
                'published_at' => '2026-05-12 09:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Desayunos solidarios de M. Paulina',
                'slug' => 'desayunos-solidarios-de-m-paulina',
                'excerpt' => 'Actividad tradicional orientada al cuidado, fraternidad y participación de los distintos estamentos.',
                'body' => 'Actividad tradicional orientada al cuidado, fraternidad y participación de los distintos estamentos.',
                'category' => 'Comunidad',
                'author_name' => 'Colegio Nuestra Señora del Carmen',
                'external_image_url' => 'niceschool/assets/img/blog/blog-post-3.webp',
                'image_alt' => 'Desayunos solidarios',
                'status' => 'published',
                'featured' => false,
                'sort_order' => 3,
                'published_at' => '2026-05-08 09:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function installSiteNewsModule(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();

        foreach ([
            ['slug' => 'ver_noticias', 'name' => 'Ver Noticias del Sitio Web'],
            ['slug' => 'gestionar_noticias', 'name' => 'Gestionar Noticias del Sitio Web'],
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
            ['slug' => 'public_site_news'],
            [
                'name' => 'Noticias',
                'frontend_route' => '/admin/noticias',
                'icon' => null,
                'sort_order' => 1,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_role') || !Schema::hasTable('role_system_module')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'administrador', 'direccion'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['ver_noticias', 'gestionar_noticias'])
            ->pluck('id');

        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['public_site', 'public_site_news'])
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
