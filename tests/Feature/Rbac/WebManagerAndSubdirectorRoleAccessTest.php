<?php

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebManagerAndSubdirectorRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const WEB_MANAGER_PERMISSIONS = [
        'ver_metricas_sitio',
        'ver_noticias',
        'gestionar_noticias',
        'ver_eventos',
        'gestionar_eventos',
        'ver_testimonios',
        'gestionar_testimonios',
        'ver_vida_estudiantil',
        'gestionar_vida_estudiantil',
        'ver_instalaciones_sitio',
        'gestionar_instalaciones_sitio',
        'ver_cgpa_sitio',
        'gestionar_cgpa_sitio',
        'ver_cde_sitio',
        'gestionar_cde_sitio',
        'ver_comite_paritario_sitio',
        'gestionar_comite_paritario_sitio',
        'ver_contactos_sitio',
        'gestionar_contactos_sitio',
    ];

    private const WEB_MANAGER_MODULES = [
        'public_site',
        'public_site_analytics',
        'public_site_news',
        'public_site_events',
        'public_site_contacts',
        'public_site_testimonials',
        'public_site_student_life',
        'public_site_cgpa',
        'public_site_cde',
        'public_site_joint_committee',
        'public_site_installations',
    ];

    private const SUBDIRECTOR_PARENT_MODULES = [
        'convivencia',
        'psychology',
        'social_work',
        'orientation',
        'operational_management',
        'spaces',
        'inspectoria',
        'superadmin',
        'pedagogical_management',
        'infirmary',
        'centro_apuntes',
        'biblioteca',
    ];

    private const SUBDIRECTOR_REQUIRED_PERMISSIONS = [
        'ver_convivencia',
        'ver_dashboard_convivencia',
        'ver_casos_convivencia',
        'psychology.access',
        'psychology.referrals.view_all',
        'psychology.cases.view_all',
        'psychology.reports.aggregate',
        'social_work.dashboard.view',
        'social_work.students.view',
        'social_work.student_profile.view',
        'social_work.cases.view',
        'orientation.view',
        'ver_traslados_operativos',
        'visar_traslados_operativos',
        'rrhh.ausencias.ver',
        'rrhh.seleccion.ver',
        'ver_dependencias',
        'ver_reservas',
        'ver_modulo_inspectoria',
        'ver_bitacora_inspectoria',
        'superadmin.logbooks.view',
        'pedagogical-instruments.view',
        'pedagogical-instruments.view-all',
        'pedagogical-instruments.statistics',
        'class-presentations.view',
        'class-presentations.view-all',
        'ver_enfermeria',
        'ver_bitacora_enfermeria',
        'ver_modulo_centro_apuntes',
        'ver_reportes_centro_apuntes',
        'ver_modulo_biblioteca',
        'ver_estadisticas_biblioteca',
    ];

    private const SUBDIRECTOR_EXCLUDED_SENSITIVE_OR_MANAGEMENT_PERMISSIONS = [
        'psychology.sensitive.override',
        'psychology.sessions.view_private',
        'psychology.reports.nominal',
        'social_work.confidential.view',
        'social_work.highly_confidential.view',
        'administrar_configuraciones_convivencia',
        'gestionar_categorias_biblioteca',
        'administrar_inventario_biblioteca',
        'aprobar_reservas',
        'rechazar_reservas',
    ];

    public function test_web_manager_has_the_complete_publication_profile_and_navigation(): void
    {
        $role = Role::query()
            ->with(['permissions:id,slug', 'modules:id,slug'])
            ->where('slug', 'encargado_web')
            ->firstOrFail();

        $this->assertSame('Encargado Web', $role->name);
        $this->assertTrue($role->active);
        $this->assertEmpty(array_diff(self::WEB_MANAGER_PERMISSIONS, $role->permissions->pluck('slug')->all()));
        $this->assertEmpty(array_diff(self::WEB_MANAGER_MODULES, $role->modules->pluck('slug')->all()));

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/news')->assertOk();
        $this->getJson('/api/admin/events')->assertOk();
        $this->postJson('/api/admin/news', [])->assertUnprocessable();
        $this->postJson('/api/admin/events', [])->assertUnprocessable();

        $navigation = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug')->all();
        $this->assertEmpty(array_diff(self::WEB_MANAGER_MODULES, $navigation));
    }

    public function test_subdirector_has_requested_supervision_modules_without_private_or_management_overrides(): void
    {
        $role = Role::query()
            ->with(['permissions:id,slug', 'modules:id,slug'])
            ->where('slug', 'subdirector')
            ->firstOrFail();

        $permissions = $role->permissions->pluck('slug')->all();
        $modules = $role->modules->pluck('slug')->all();

        $this->assertSame('Subdirector', $role->name);
        $this->assertTrue($role->active);
        $missingPermissions = array_values(array_diff(self::SUBDIRECTOR_REQUIRED_PERMISSIONS, $permissions));
        $this->assertEmpty($missingPermissions, 'Permisos faltantes: '.implode(', ', $missingPermissions));
        $missingModules = array_values(array_diff(self::SUBDIRECTOR_PARENT_MODULES, $modules));
        $this->assertEmpty($missingModules, 'Módulos faltantes: '.implode(', ', $missingModules));
        $this->assertEmpty(array_intersect(self::SUBDIRECTOR_EXCLUDED_SENSITIVE_OR_MANAGEMENT_PERMISSIONS, $permissions));

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/superadmin/logbooks')->assertOk();
        $this->getJson('/api/biblioteca/dashboard')->assertOk();
        $this->getJson('/api/centro-apuntes/dashboard')->assertOk();
        $this->getJson('/api/spaces/dependencies')->assertOk();
        $this->postJson('/api/biblioteca/categorias', [])->assertForbidden();

        $navigation = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug')->all();
        $this->assertEmpty(array_diff(self::SUBDIRECTOR_PARENT_MODULES, $navigation));
        $this->assertContains('superadmin_logbook_review', $navigation);
        $this->assertNotContains('superadmin_reloj_control', $navigation);
        $this->assertNotContains('psychology_configuration', $navigation);
    }

    public function test_mutating_library_and_reservation_routes_require_specific_management_permissions(): void
    {
        $this->assertRouteUsesPermission('POST', '/api/biblioteca/categorias', 'permission:gestionar_categorias_biblioteca');
        $this->assertRouteUsesPermission('POST', '/api/biblioteca/ubicaciones', 'permission:gestionar_almacenaje_biblioteca');
        $this->assertRouteUsesPermission('POST', '/api/biblioteca/materiales', 'permission:gestionar_materiales_biblioteca');
        $this->assertRouteUsesPermission('POST', '/api/biblioteca/textos-escolares/recepciones', 'permission:gestionar_textos_escolares_biblioteca');
        $this->assertRouteUsesPermission('POST', '/api/biblioteca/pases', 'permission:gestionar_pases_biblioteca');
        $this->assertRouteUsesPermission('PUT', '/api/spaces/reservations/999/approve', 'permission:aprobar_reservas');
        $this->assertRouteUsesPermission('PUT', '/api/spaces/reservations/999/reject', 'permission:rechazar_reservas');
    }

    public function test_role_migration_is_idempotent_and_canonical_seed_preserves_both_profiles(): void
    {
        $webManager = Role::query()->where('slug', 'encargado_web')->firstOrFail();
        $subdirector = Role::query()->where('slug', 'subdirector')->firstOrFail();
        $counts = [
            'web_permissions' => $webManager->permissions()->count(),
            'web_modules' => $webManager->modules()->count(),
            'subdirector_permissions' => $subdirector->permissions()->count(),
            'subdirector_modules' => $subdirector->modules()->count(),
        ];

        $migration = require database_path('migrations/2026_09_06_210000_create_web_manager_and_expand_subdirector_roles.php');
        $migration->up();

        $this->assertSame(1, Role::query()->where('slug', 'encargado_web')->count());
        $this->assertSame(1, Role::query()->where('slug', 'subdirector')->count());
        $this->assertSame($counts['web_permissions'], $webManager->permissions()->count());
        $this->assertSame($counts['web_modules'], $webManager->modules()->count());
        $this->assertSame($counts['subdirector_permissions'], $subdirector->permissions()->count());
        $this->assertSame($counts['subdirector_modules'], $subdirector->modules()->count());

        $this->seed(RbacSeeder::class);

        $webManager->refresh()->load(['permissions:id,slug', 'modules:id,slug']);
        $subdirector->refresh()->load(['permissions:id,slug', 'modules:id,slug']);

        $this->assertEmpty(array_diff(self::WEB_MANAGER_PERMISSIONS, $webManager->permissions->pluck('slug')->all()));
        $this->assertEmpty(array_diff(self::WEB_MANAGER_MODULES, $webManager->modules->pluck('slug')->all()));
        $missingPermissions = array_values(array_diff(
            self::SUBDIRECTOR_REQUIRED_PERMISSIONS,
            $subdirector->permissions->pluck('slug')->all(),
        ));
        $this->assertEmpty($missingPermissions, 'Permisos faltantes tras RbacSeeder: '.implode(', ', $missingPermissions));
        $this->assertEmpty(array_diff(self::SUBDIRECTOR_PARENT_MODULES, $subdirector->modules->pluck('slug')->all()));

        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $this->assertTrue($superAdmin->permissions()->whereIn('slug', [
            'superadmin.logbooks.view',
            'operational_logbook.view',
            'operational_logbook.create',
        ])->count() === 3);
        $this->assertTrue($superAdmin->modules()->where('slug', 'operational_staff_logbook')->exists());
    }

    private function assertRouteUsesPermission(string $method, string $uri, string $permission): void
    {
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));

        $this->assertContains($permission, $route->gatherMiddleware());
    }
}
